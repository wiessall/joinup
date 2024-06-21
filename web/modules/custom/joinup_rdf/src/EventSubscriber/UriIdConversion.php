<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\sparql_entity_storage\Event\InboundValueEvent;
use Drupal\sparql_entity_storage\Event\OutboundValueEvent;
use Drupal\sparql_entity_storage\Event\SparqlEntityStorageEvents;
use Drupal\sparql_entity_storage\Event\ValueEventBase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to entity ID inbound/outbound value.
 *
 * This is a temporary solution needed while converting entities with RDF
 * storage to entities with default Drupal storage. During this process, there
 * are entities still not converted, thus their entity reference fields are
 * stored as URIs in the RDF triplestore. But the target entities were already
 * converted to use the MySQL backend and their IDs are numeric (nodes, terms).
 * We listen to the entity reference field target_id being loaded or saved, and
 * perform the necessary conversion, from URI to numeric ID or vice-versa.
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
    'rdf_entity:asset_release:field_isr_contact_information',
    'rdf_entity:asset_release:field_isr_language',
    'rdf_entity:asset_release:field_isr_solution_type',
    'rdf_entity:asset_release:field_spatial_coverage',
    'rdf_entity:asset_release:field_status',
    'rdf_entity:asset_release:field_topic',
    'rdf_entity:collection:field_ar_contact_information',
    'rdf_entity:collection:field_spatial_coverage',
    'rdf_entity:collection:field_topic',
    'rdf_entity:solution:field_is_actor',
    'rdf_entity:solution:field_is_business_process',
    'rdf_entity:solution:field_is_contact_information',
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
    'rdf_meta_entity:eif_perspective:eif_principle',
    'rdf_meta_entity:eif_perspective:field_eif_solution_type',
    'rdf_entity:asset_release:field_isr_owner',
    'rdf_entity:collection:field_ar_owner',
    'rdf_entity:solution:field_is_owner',
  ];

  /**
   * Static cache for the URI <-> ID relation.
   *
   * Keys are URIs, values are tuples where the first element (0) is the entity
   * type ID and the second element (1) is the entity ID.
   *
   * @var array[]
   */
  private array $uriToId = [];

  /**
   * Static cache for the ID <-> URI relation.
   *
   * Keys are entity types, values are arrays of URIs keyed by entity ID.
   *
   * @var array[]
   */
  private array $idToURI = [];

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly LoggerChannelInterface $logger,
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
      $event->setValue($this->getId($event));
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
   * Returns and caches an entity ID given its corresponding URI.
   *
   * @param \Drupal\sparql_entity_storage\Event\InboundValueEvent $event
   *   The event.
   *
   * @return string|int|null
   *   The entity ID.
   *
   * @throws \InvalidArgumentException
   *   If there's no entity mapped with the passed URI.
   */
  protected function getId(InboundValueEvent $event): string|int|null {
    $uri = $event->getValue();
    if (!isset($this->uriToId[$uri])) {
      $result = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['entity_type', 'entity_id'])
        ->condition('uri', $uri)
        ->execute()
        ->fetch(\PDO::FETCH_NUM);
      if (!$result) {
        $this->logger->error("Can't find a mapped entity with URI $uri");
      }
      else {
        $this->idToURI[$result[0]][$result[1]] = $uri;
      }
      $this->uriToId[$uri] = $result;
    }
    return $this->uriToId[$uri][1] ?? NULL;
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
   *
   * @throws \InvalidArgumentException
   *   If there's no mapped URI for the passed entity.
   */
  protected function getUri(string $entityTypeId, int|string|RdfSyncEntityInterface $entity): ?string {
    $id = $entity instanceof RdfSyncEntityInterface ? (string) $entity->id() : (string) $entity;
    if (!isset($this->idToURI[$entityTypeId][$id])) {
      $uri = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['uri'])
        ->condition('entity_type', $entityTypeId)
        ->condition('entity_id', $id)
        ->execute()
        ->fetchField() ?: NULL;
      if (!$uri) {
        $this->logger->error("Can't find a URI for $entityTypeId entity with ID $id");
      }
      else {
        $this->uriToId[$uri] = [$entityTypeId, $id];
      }
      // Cache this match.
      $this->idToURI[$entityTypeId][$id] = $uri;
    }
    return $this->idToURI[$entityTypeId][$id];
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
