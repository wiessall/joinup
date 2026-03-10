<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Plugin\facets\processor;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\SearchApiFacetSourceInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty;
use Drupal\search_api\Utility\Utility;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces the bundle machine name with its label in aggregated fields.
 *
 * @FacetsProcessor(
 *   id = "aggregated_entity_reference_label",
 *   label = @Translation("Transform aggregated entity references into labels"),
 *   description = @Translation("Replaces entity references ids with labels. Works only with aggregated fields."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class AggregatedEntityReferenceLabel extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerInterface $logger,
    protected readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('joinup_core'),
      $container->get('entity.definition_update_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // Bail out early if the source is not coming from Search API.
    $source = $facet->getFacetSource();
    if (!$source instanceof SearchApiFacetSourceInterface) {
      return $results;
    }

    // Retrieve the index field from the index.
    /** @var \Drupal\facets\FacetSource\SearchApiFacetSourceInterface $source */
    $index = $source->getIndex();
    $field_id = $facet->getFieldIdentifier();
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $field = $index->getField($field_id);

    // This processor handles only aggregated fields.
    if (!$field->getDataDefinition() instanceof AggregatedFieldProperty) {
      return $results;
    }

    // Loop all the fields that are part of this aggregated field, and retrieve
    // the field definition associated with them.
    $reference_types = [];
    foreach ($field->getConfiguration()['fields'] as $combined_id) {
      $nested_field_storage = NULL;
      [$datasource_id, $property_path] = Utility::splitCombinedId($combined_id);
      $properties = $index->getPropertyDefinitions($datasource_id);
      [$referenced_field, $nested_field] = Utility::splitPropertyPath($property_path, FALSE);
      if (!empty($nested_field)) {
        $property_path = $referenced_field;
        [, $nested_field] = Utility::splitPropertyPath($nested_field);
        [, $entity_type_id] = Utility::splitPropertyPath($datasource_id);
        $nested_field_storage = $this->entityDefinitionUpdateManager->getFieldStorageDefinition($nested_field, $entity_type_id);
      }
      if (isset($properties[$property_path])) {
        $property = $properties[$property_path];

        // When the field is a entity reference or an OG group audience field,
        // collect the target type.
        if ($property instanceof FieldDefinitionInterface && in_array(
          $property->getType(),
          ['entity_reference', 'og_standard_reference']
        )) {
          $target_type = ($nested_field_storage instanceof FieldStorageDefinitionInterface) ? $nested_field_storage->getSetting('target_type') : $property->getSetting('target_type');

          // Ensure that the entry exists in the array.
          $reference_types += [$target_type => 0];
          $reference_types[$target_type] += 1;
        }
      }
    }

    // Extract all the raw values in a disposable array. This array will be
    // reduced each time a label replacement is made. This allows to avoid
    // entity loads when the entity is already found.
    $ids = [];
    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as $delta => $result) {
      $ids[$delta] = $result->getRawValue();
    }

    // Sort the reference types by their value, so the entity types that are
    // more represented in the field are processed first.
    // This "optimisation" isn't maybe really useful, but it doesn't hurt.
    arsort($reference_types);

    // Loop through all the entity types, and try to load each raw value.
    // This of course will fail if multiple entity types share the same value
    // in an aggregated field.
    // But this use case should be avoided in the first place by developers,
    // as storing indistinguishable data has questionable value.
    foreach (array_keys($reference_types) as $entity_type) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      foreach ($ids as $delta => $raw_value) {
        try {
          $entity = $storage->load($raw_value);
          if ($entity && !empty($entity->label())) {
            $results[$delta]->setDisplayValue($entity->label());
            unset($ids[$delta]);
          }
        }
        catch (\Exception $exception) {
          // Whoa, something really bad happened.
          $this->logger->error('Error processing facet raw value: cannot load entity with id @id for entity type @type', [
            '@id' => $raw_value,
            '@type' => $entity_type,
          ]);
        }
      }
    }

    return $results;
  }

}
