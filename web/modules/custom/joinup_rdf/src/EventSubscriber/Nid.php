<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Drupal\sparql_entity_storage\Event\InboundValueEvent;
use Drupal\sparql_entity_storage\Event\OutboundValueEvent;
use Drupal\sparql_entity_storage\Event\SparqlEntityStorageEvents;
use Drupal\sparql_entity_storage\Event\ValueEventBase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to node ID inbound/outbound value.
 *
 * @todo Drop in ISAICP-8376
 */
class Nid implements EventSubscriberInterface {

  /**
   * Fields that should allow NID <-> URI conversion.
   *
   * @const string[]
   */
  private const MAP = [
    'rdf_entity:asset_release:field_isr_owner',
    'rdf_entity:collection:field_ar_owner',
    'rdf_entity:solution:field_is_owner',
  ];

  /**
   * Static cache for the URI <-> NID relation.
   *
   * @var int[]
   */
  private array $uriToNid = [];

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    private readonly Connection $database,
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
      $event->setValue($this->getNid($event->getValue()));
    }
  }

  /**
   * Listens to a value bing saved from Drupal entity/field API to RDF storage.
   *
   * @param \Drupal\sparql_entity_storage\Event\OutboundValueEvent $event
   *   The event.
   */
  public function onSave(OutboundValueEvent $event): void {
    if ($this->acceptsConversion($event)) {
      $event->setValue($this->getUri($event->getValue()));
    }
  }

  /**
   * Returns and caches a node ID give its corresponding URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string|int|null
   *   The node ID.
   */
  protected function getNid(string $uri): string|int|null {
    if (!($this->uriToNid[$uri] ?? NULL)) {
      $this->uriToNid[$uri] = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['entity_id'])
        ->condition('uri', $uri)
        ->execute()
        ->fetchField() ?: NULL;
    }
    return $this->uriToNid[$uri];
  }

  /**
   * Returns and caches a URI given its corresponding node ID.
   *
   * @param int|string|NodeInterface $node
   *   The node as entity or as node ID.
   *
   * @return string|null
   *   The URI.
   */
  protected function getUri(int|string|NodeInterface $node): ?string {
    $nid = $node instanceof NodeInterface ? (string) $node->id() : (string) $node;
    if (($uri = array_search($nid, $this->uriToNid, TRUE)) === FALSE) {
      $uri = $this->database->select('rdf_sync_uri')
        ->fields('rdf_sync_uri', ['uri'])
        ->condition('entity_type', 'node')
        ->condition('entity_id', $nid)
        ->execute()
        ->fetchField() ?: NULL;
      // Cache this match.
      $this->uriToNid[$uri] = $nid;
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
