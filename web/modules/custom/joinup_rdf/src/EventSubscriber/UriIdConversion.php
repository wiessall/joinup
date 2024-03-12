<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\sparql_entity_storage\Event\InboundValueEvent;
use Drupal\sparql_entity_storage\Event\OutboundValueEvent;
use Drupal\sparql_entity_storage\Event\SparqlEntityStorageEvents;
use Drupal\sparql_entity_storage\Event\ValueEventBase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to entity ID inbound/outbound value.
 *
 * @todo Drop in ISAICP-8376
 */
class UriIdConversion implements EventSubscriberInterface {

  /**
   * Fields that should allow TID <-> URI conversion.
   *
   * @const string[]
   */
  private const MAP = [
    'eif_wrapper:recommendation:term',
    'rdf_entity:asset_distribution:field_ad_format',
    'rdf_entity:asset_distribution:field_ad_licence',
    'rdf_entity:asset_distribution:field_ad_repr_technique',
    'rdf_entity:asset_distribution:field_status',
    'rdf_entity:asset_release:field_isr_language',
    'rdf_entity:asset_release:field_spatial_coverage',
    'rdf_entity:asset_release:field_status',
    'rdf_entity:asset_release:field_topic',
    'rdf_entity:collection:field_spatial_coverage',
    'rdf_entity:collection:field_topic',
    'rdf_entity:owner:field_owner_type',
    'rdf_entity:owner:field_owner_type',
    'rdf_entity:solution:field_is_actor',
    'rdf_entity:solution:field_is_business_process',
    'rdf_entity:solution:field_is_eif_recommendation',
    'rdf_entity:solution:field_is_language',
    'rdf_entity:solution:field_is_product_type',
    'rdf_entity:solution:field_is_solution_type',
    'rdf_entity:solution:field_is_standardization_level',
    'rdf_entity:solution:field_is_test_resource_type',
    'rdf_entity:solution:field_is_test_resource_type',
    'rdf_entity:solution:field_spatial_coverage',
    'rdf_entity:solution:field_status',
    'rdf_entity:solution:field_topic',
    'rdf_meta_entity:eif_perspective:eif_layer',
    'rdf_meta_entity:eif_perspective:eif_model',
    'rdf_meta_entity:eif_perspective:field_eif_solution_type',
  ];

  /**
   * Static cache for the URI <-> ID relation.
   *
   * @var array
   */
  private array $uriToId = [];

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SparqlEntityStorageEvents::INBOUND_VALUE => 'onLoad',
      SparqlEntityStorageEvents::OUTBOUND_VALUE => 'onSave',
    ];
  }

  /**
   * Listens to a value coming from RDF storage to Drupal entity/field API.
   *
   * @param \Drupal\sparql_entity_storage\Event\InboundValueEvent $event
   *   The event.
   */
  public function onLoad(InboundValueEvent $event): void {
    if ($this->acceptsConversion($event)) {
      $event->setValue($this->getId($event->getValue()));
    }
  }

  /**
   * Listens to a value being saved from Drupal entity/field API to RDF storage.
   *
   * @param \Drupal\sparql_entity_storage\Event\OutboundValueEvent $event
   *   The event.
   */
  public function onSave(OutboundValueEvent $event): void {
    if ($this->acceptsConversion($event)) {
      $storageDefinition = $this->entityFieldManager->getFieldStorageDefinitions($event->getEntityTypeId())[$event->getField()];
      $targetEntityTypeId = $storageDefinition->getSetting('target_type');
      $event->setValue($this->getUri($targetEntityTypeId, $event->getValue()));
    }
  }

  /**
   * Returns and caches an entity ID give its corresponding URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string|int|null
   *   The entity ID.
   */
  protected function getId(string $uri): string|int|null {
    if (!($this->uriToId[$uri] ?? NULL)) {
      $this->uriToId[$uri] = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['entity_id'])
        ->condition('uri', $uri)
        ->execute()
        ->fetchField() ?: NULL;
    }
    return $this->uriToId[$uri];
  }

  /**
   * Returns and caches a URI given its corresponding entity ID.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param int|string|\Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The entity as entity instance or as entity ID.
   *
   * @return string|null
   *   The URI.
   */
  protected function getUri(string $entityTypeId, int|string|RdfSyncEntityInterface $entity): ?string {
    $id = $entity instanceof RdfSyncEntityInterface ? (string) $entity->id() : (string) $entity;
    if (($uri = array_search($id, $this->uriToId, TRUE)) === FALSE) {
      $uri = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['uri'])
        ->condition('entity_type', $entityTypeId)
        ->condition('entity_id', $id)
        ->execute()
        ->fetchField() ?: NULL;
      // Cache this match.
      $this->uriToId[$uri] = $id;
    }
    return $uri;
  }

  /**
   * Checks if a value qualifies for conversion.
   *
   * @param \Drupal\sparql_entity_storage\Event\ValueEventBase $event
   *   The event.
   *
   * @return bool
   *   Whether to convert the value passed via $event.
   */
  private function acceptsConversion(ValueEventBase $event): bool {
    return in_array("{$event->getEntityTypeId()}:{$event->getBundle()}:{$event->getField()}", self::MAP, TRUE);
  }

}
