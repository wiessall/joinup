<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\asset_release\Entity\AssetRelease;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\solution\Entity\Solution;
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
    'rdf_entity:asset_distribution:field_ad_format',
    'rdf_entity:asset_distribution:field_ad_licence',
    'rdf_entity:asset_distribution:field_ad_repr_technique',
    'rdf_entity:asset_distribution:field_isr_distribution',
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
    'rdf_entity:solution:field_is_distribution',
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
   * Keys are URIs, values are tuples where the first element (0) is the entity
   * type ID and the second element (1) is the entity ID.
   *
   * @var array[]
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
        ->fetch(\PDO::FETCH_NUM) ?: NULL;
      if (!$result) {
        // Prevent the case when solutions and releases are not yet converted
        // during de update process. These fields from solution and release are
        // linking back to distributions. The only reason they existed in Drupal
        // is to fulfill the ADMS-AP specification, where the reference is
        // wrongly defined from parent to child. In ISAICP-8569 and ISAICP-8572,
        // these fields will be removed from release/solution, sticking to the
        // normal Drupal relationship, from child to parent. To honour the
        // ADMS-AP specification, we will add a triple from asset to its
        // distributions, with the http://www.w3.org/ns/dcat#distribution
        // predicate, when we're normalizing the release/solution.
        // @todo Gradually remove this check and assertions as releases and
        //   solution are converted, first in ISAICP-8569, later in ISAICP-8572.
        assert(!method_exists(AssetRelease::class, 'getUri'));
        assert(!method_exists(Solution::class, 'getUri'));
        if (in_array($event->getField(), ['field_is_distribution', 'field_isr_distribution'], TRUE)) {
          $result = ['node', NULL];
        }
        else {
          throw new \InvalidArgumentException("Can't find a mapped entity with URI $uri");
        }
      }
      $this->uriToId[$uri] = $result;
    }
    return $this->uriToId[$uri][1];
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
    if (($uri = array_search([$entityTypeId, $id], $this->uriToId, TRUE)) === FALSE) {
      $uri = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['uri'])
        ->condition('entity_type', $entityTypeId)
        ->condition('entity_id', $id)
        ->execute()
        ->fetchField() ?: NULL;
      if (!$uri) {
        throw new \InvalidArgumentException("Can't find a URI for $entityTypeId entity with ID $id");
      }
      // Cache this match.
      $this->uriToId[$uri] = [$entityTypeId, $id];
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
