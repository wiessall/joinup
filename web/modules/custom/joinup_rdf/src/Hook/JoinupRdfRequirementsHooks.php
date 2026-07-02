<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Drupal\rdf_sync\RdfSyncSynchronizer;
use EasyRdf\Serialiser\Ntriples;
use Joinup\EnvironmentDetector;

/**
 * Requirements hook implementations for the Joinup RDF module.
 */
final class JoinupRdfRequirementsHooks {

  use StringTranslationTrait;

  /**
   * Number of URIs verified per SPARQL query.
   */
  private const int BATCH_SIZE = 500;

  public function __construct(
    protected readonly Connection $database,
    protected readonly RdfSyncConnectionInterface $connection,
    protected readonly RdfSyncSynchronizer $synchronizer,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    // These checks query the live triplestore, which only exists on
    // production; elsewhere (local, CI, acceptance) the graph is expected to be
    // out of sync.
    if (!EnvironmentDetector::isProduction()) {
      return [];
    }

    return array_merge(
      $this->checkSynchronization(),
      $this->checkEndpointData(),
    );
  }

  /**
   * Checks whether automatic RDF synchronization is enabled.
   *
   * @return array
   *   A requirements array, empty when synchronization is enabled.
   */
  private function checkSynchronization(): array {
    if ($this->synchronizer->isSynchronizationEnabled()) {
      return [];
    }

    return [
      'joinup_rdf_synchronization' => [
        'title' => $this->t('RDF synchronization'),
        'value' => $this->t('Disabled'),
        'description' => $this->t('Automatic synchronization to the SPARQL endpoint is turned off. Entity changes are not being exported to the triplestore. Re-enable it with <code>drush rdf_sync:enable</code>.'),
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

  /**
   * Verifies that tracked entities are present in the SPARQL endpoint.
   *
   * @return array
   *   A requirement array reporting either that all tracked entities are
   *   present or a per-entity-type breakdown of the missing ones.
   */
  private function checkEndpointData(): array {
    $graphUri = $this->configFactory->get('rdf_sync.settings')->get('graph_uri');

    try {
      $stats = $this->gatherEndpointStats($graphUri);
    }
    catch (\Exception $e) {
      return [
        'joinup_rdf_endpoint_data' => [
          'title' => $this->t('RDF endpoint data'),
          'value' => $this->t('Unable to verify the SPARQL endpoint data.'),
          'description' => $this->t('The check for entities missing from the SPARQL endpoint could not be completed: @message', ['@message' => $e->getMessage()]),
          'severity' => RequirementSeverity::Warning,
        ],
      ];
    }

    $missing = array_sum(array_column($stats, 'missing'));
    $total = array_sum(array_column($stats, 'total'));

    if ($missing === 0) {
      return [
        'joinup_rdf_endpoint_data' => [
          'title' => $this->t('RDF endpoint data'),
          'value' => $this->t('All @total tracked entities are present in the SPARQL endpoint.', ['@total' => $total]),
          'severity' => RequirementSeverity::OK,
        ],
      ];
    }

    // List only the entity types that are missing data, with a present/expected
    // breakdown; the individual URIs are intentionally omitted as the list can
    // be very long.
    $items = [];
    foreach ($stats as $entityType => $stat) {
      if ($stat['missing'] === 0) {
        continue;
      }
      $items[] = $this->t('@type: @present of @total present (@missing missing)', [
        '@type' => $entityType,
        '@present' => $stat['present'],
        '@total' => $stat['total'],
        '@missing' => $stat['missing'],
      ]);
    }

    return [
      'joinup_rdf_endpoint_data' => [
        'title' => $this->t('RDF endpoint data'),
        'value' => $this->t('@missing of @total tracked entities are missing from the SPARQL endpoint', [
          '@missing' => $missing,
          '@total' => $total,
        ]),
        'description' => [
          'summary' => [
            '#markup' => $this->t('Some entities are tracked as synchronised but are absent from the graph (@graph) and should be re-exported to Virtuoso.', ['@graph' => $graphUri]),
          ],
          'breakdown' => [
            '#theme' => 'item_list',
            '#items' => $items,
          ],
        ],
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

  /**
   * Counts tracked versus present entities per entity type in the endpoint.
   *
   * @param string $graphUri
   *   The graph URI to query.
   *
   * @return array<string, array{total: int, present: int, missing: int}>
   *   The statistics are keyed by the entity type.
   */
  private function gatherEndpointStats(string $graphUri): array {
    $serializer = new Ntriples();

    // Expected count of tracked entities per entity type.
    $expected = $this->database->select('rdf_sync_uri', 'u');
    $expected->addField('u', 'entity_type', 'entity_type');
    $expected->addExpression('COUNT(*)', 'total');
    $expected->groupBy('u.entity_type');
    $totals = $expected->execute()->fetchAllKeyed();

    $stats = [];
    foreach ($totals as $entityType => $total) {
      $total = (int) $total;
      $present = 0;
      $offset = 0;

      while ($offset < $total) {
        // {rdf_sync_uri} has no numeric key, so paginate on a stable sort.
        $uris = $this->database->select('rdf_sync_uri', 'u')
          ->fields('u', ['uri'])
          ->condition('u.entity_type', $entityType)
          ->orderBy('u.entity_id')
          ->range($offset, self::BATCH_SIZE)
          ->execute()
          ->fetchCol();

        if (!$uris) {
          break;
        }
        $offset += count($uris);

        $serialized = array_map(
          static fn(string $uri): string => $serializer->serialiseValue(['type' => 'uri', 'value' => $uri]),
          $uris,
        );
        $values = implode(' ', $serialized);
        $result = $this->connection->query("SELECT (COUNT(DISTINCT ?s) AS ?present) FROM <$graphUri> WHERE { VALUES ?s { $values } ?s ?p ?o }");
        foreach ($result as $row) {
          $present += (int) (string) $row->present;
        }
      }

      $stats[$entityType] = [
        'total' => $total,
        'present' => $present,
        'missing' => $total - $present,
      ];
    }

    return $stats;
  }

}
