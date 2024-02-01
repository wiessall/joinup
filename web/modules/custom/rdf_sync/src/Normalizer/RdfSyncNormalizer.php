<?php

declare(strict_types=1);

namespace Drupal\rdf_sync\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\rdf_sync\Encoder\RdfSyncEncoder;
use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\serialization\Normalizer\EntityNormalizer;
use EasyRdf\Literal;
use EasyRdf\Resource;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Normalizes data to an EasyRDF graph PHP representation.
 */
class RdfSyncNormalizer extends EntityNormalizer {

  /**
   * Constructs a new normalizer instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF sync mapper service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeRepositoryInterface $entity_type_repository,
    EntityFieldManagerInterface $entity_field_manager,
    protected RdfSyncMapper $mapper,
    protected LanguageManagerInterface $languageManager,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($entity_type_manager, $entity_type_repository, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [RdfSyncEntityInterface::class => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array {
    /** @var \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $object */
    $normalized = parent::normalize($object, $format, $context);

    $id = new Resource($object->getUri());
    $entityType = $object->getEntityType();
    unset($normalized[$entityType->getKey('id')]);
    $mappings = $this->mapper->getMappings($entityType->id(), $object->bundle());
    $rdfType = $this->mapper->getBundleRdfType($entityType->id(), $object->bundle());

    $rdf = [];
    if (!$hasBundleMapping = $this->mapper->hasBundleMapping($object)) {
      $rdf[$id->getUri()]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][] = (new Resource($rdfType))->toRdfPhp();
    }

    foreach ($normalized as $fieldName => $items) {
      foreach ($items as $item) {
        foreach ($item as $columnName => $value) {
          if (!$mapping = ($mappings[$fieldName][$columnName] ?? NULL)) {
            // This column is not mapped.
            continue;
          }

          $fieldDefinition = $object->getFieldDefinition($fieldName);
          $itemDefinition = $fieldDefinition->getItemDefinition();

          if ($fieldName === $entityType->getKey('bundle')) {
            if ($hasBundleMapping) {
              // This entity has bundle mapping. Taxonomy terms, for instance,
              // need the bundle mapping to refer to the vocabulary resource.
              $value = $rdfType;
            }
          }
          // Resolve references to other entities.
          elseif (is_a(EntityReferenceItem::class, $itemDefinition->getClass(), TRUE)) {
            /** @var \Drupal\Core\Entity\EntityInterface $entity */
            if (!$entity = $object->get($fieldName)->entity) {
              $value = NULL;
            }
            elseif ($this->mapper->isMappedBundle($entity->getEntityTypeId(), $entity->bundle())) {
              // If the destination is also mapped, replace the scalar ID (int,
              // string) with the URI.
              $value = $object->get($fieldName)->entity->getUri();
            }
          }

          if ($value === NULL) {
            // Don't store NULLs.
            continue;
          }

          // phpcs:disable
          // @todo implement transformations against values. A list of callables
          //   might be stored in $mapping. If present iterate over all and do
          //   cascade transformations against the value. Other option could be
          //   the current Inbound/OutboundValue events and subscribers.
          // phpcs:enable

          // Set the langcode of value.
          $langCode = NULL;
          if ($fieldDefinition->isTranslatable() && !$this->languageManager->isLanguageLocked($object->get($fieldName)->getLangcode())) {
            $langCode = $object->get($fieldName)->getLangcode();
          }

          $value = $mapping->type === 'resource' ? new Resource($value) : Literal::create($value, $langCode, $mapping->type);
          $predicate = new Resource($mapping->predicate);
          $rdf[$id->getUri()][$predicate->getUri()][] = $value->toRdfPhp();
        }
      }
    }

    // Allow 3rd-party to alter the normalized array.
    $event = new RdfSyncNormalizeEvent($rdf, $object);
    $this->eventDispatcher->dispatch($event);

    return $event->getNormalizedArray();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, string $type, ?string $format = NULL, array $context = []): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL): bool {
    return !empty(RdfSyncEncoder::getSupportedFormats()[$format]);
  }

}
