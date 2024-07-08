<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\search_api\processor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\joinup_distribution\ParentDistributionResolverInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indexes the distribution data into the parent solution or release fields.
 *
 * Distributions are not shown in the site-wide search results. Instead, their
 * data (title, description, URL, licence, etc) is indexed in the parent Search
 * API record, so that searching for a keyword belonging to a distribution will
 * retrieve their parent (release or solution).
 *
 * @SearchApiProcessor(
 *   id = "index_distribution_data",
 *   label = @Translation("Index distribution data"),
 *   description = @Translation("Indexes the distribution data into the distribution parent Search API record."),
 *   stages = {
 *     "preprocess_index" = 0,
 *   },
 * )
 */
class IndexDistributionData extends ProcessorPluginBase {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\joinup_distribution\ParentDistributionResolverInterface $parentDistributionResolver
   *   The parent distribution resolver service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ParentDistributionResolverInterface $parentDistributionResolver,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('file_url_generator'),
      $container->get('entity_type.manager'),
      $container->get('joinup_distribution.parent_distribution_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'rdf_entity') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      // Append data from child distributions.
      $this->appendDistributionData($item);
    }
  }

  /**
   * Appends the distribution data to the parent's Search API description field.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The Search API item.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when $item->getOriginalObject() parameter, $load, is TRUE but the
   *   object could not be loaded.
   */
  protected function appendDistributionData(ItemInterface $item): void {
    /** @var \Drupal\rdf_entity\RdfInterface $entity */
    $entity = $item->getOriginalObject()->getValue();

    // Only solutions and releases should receive distribution data.
    if (!in_array($entity->bundle(), ['release', 'solution'])) {
      return;
    }

    $distributionIds = $this->parentDistributionResolver->getParentsDistributionIds($entity->id());
    if (empty($distributionIds)) {
      return;
    }

    // Get the parent Search API field.
    $sapiFieldName = $entity->bundle() === 'release' ? 'description_processed' : 'field_is_description';
    $sapiField = $item->getField($sapiFieldName);

    // Iterate over all distributions and append their data to Search API field.
    /** @var \Drupal\rdf_entity\RdfInterface $distribution */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($distributionIds) as $distribution) {
      // Add the distribution title.
      $sapiField->addValue($distribution->label());

      // Add the distribution description.
      $distributionFieldItemList = $distribution->get('description');
      if (!$distributionFieldItemList->isEmpty()) {
        $sapiField->addValue(check_markup($distributionFieldItemList->value, $distributionFieldItemList->format));
      }

      // Add the distribution URL.
      /** @var \Drupal\file_url\Plugin\Field\FieldType\FileUrlFieldItemList $distributionFieldItemList */
      $distributionFieldItemList = $distribution->get('access_url');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $file) {
          $file_url = $file->getFileUri();
          if (UrlHelper::isExternal($file_url) && !UrlHelper::externalIsLocal($file_url, $GLOBALS['base_url'])) {
            $url = $file_url;
          }
          else {
            $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          }
          $sapiField->addValue($url);
        }
      }

      // Add the distribution licence.
      $distributionFieldItemList = $distribution->get('field_licence');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $licence) {
          $sapiField->addValue($licence->label());
        }
      }

      // Add the distribution format.
      $distributionFieldItemList = $distribution->get('format');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $format) {
          $sapiField->addValue($format->label());
        }
      }

      // Add the distribution representation technique.
      $distributionFieldItemList = $distribution->get('representation_technique');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $technique) {
          $sapiField->addValue($technique->label());
        }
      }
    }
  }

}
