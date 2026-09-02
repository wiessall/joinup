<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Helper class for recovering missing solution revisions.
 *
 * @see joinup_release_deploy_201600()
 */
final class SolutionRevisionRecovery implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * Affected dedicated tables.
   *
   * @var string[]
   */
  private const array DEDICATED_TABLES = [
    'abstract',
    'classification',
    'contact',
    'description',
    'domain',
    'field_state',
    'field_topic',
    'keywords',
    'landing_page',
    'language',
    'logo',
    'metrics_page',
    'og_audience',
    'owner',
    'process_status',
    'related_solutions',
    'release_number',
    'resources',
    'short_id',
    'site_featured',
    'solution_type',
  ];

  public function __construct(protected Connection $db) {}

  /**
   * Recovers missing solution revisions.
   *
   * @param array{vids: list<int|numeric-string>, total: int, progress: int, "#finished": int} $sandbox
   *   The update sandbox.
   *
   * @return string
   *   Status string for each batch.
   */
  public function run(array &$sandbox): string {
    $this->initialize($sandbox);
    $this->process($sandbox);
    $this->finish($sandbox);
    return "Recovered $sandbox[progress] out of $sandbox[total] solution revisions";
  }

  /**
   * Initializes the sandbox.
   *
   * @param array{vids: list<int|numeric-string>, total: int, progress: int, "#finished": int} $sandbox
   *   The update sandbox.
   */
  protected function initialize(array &$sandbox): void {
    if (isset($sandbox['vids'])) {
      return;
    }

    $query = $this->db->select('node_revision__solution_data', 'rd')
      ->distinct()
      ->fields('rd', ['solution_data_target_revision_id']);
    $query->leftJoin('node_field_revision', 'nr', 'rd.solution_data_target_revision_id = nr.vid');
    // Ensure the solution still exists.
    $query->innerJoin('node', 'n', 'rd.solution_data_target_id = n.nid');
    $sandbox['vids'] = $query
      ->isNull('nr.vid')
      ->orderBy('rd.solution_data_target_revision_id')
      ->execute()
      ->fetchCol();
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['vids']);
  }

  /**
   * Processes one missing solution revision.
   *
   * @param array{vids: list<int|numeric-string>, total: int, progress: int, "#finished": int} $sandbox
   *   The update sandbox.
   */
  protected function process(array &$sandbox): void {
    $vid = array_shift($sandbox['vids']);

    $schema = $this->db->schema();
    foreach ($this->getAffectedTables() as $table => $field) {
      $oldTable = "old__$table";
      if ($schema->tableExists($oldTable)) {
        $select = $this->db->select($oldTable)
          ->fields($oldTable)
          ->condition($field, $vid);
        $this->db->insert($table)->from($select)->execute();
      }
    }
    $sandbox['progress']++;

    $sandbox['#finished'] = (int) empty($sandbox['vids']);
  }

  /**
   * Finishes the batch operations.
   *
   * @param array{vids: list<int|numeric-string>, total: int, progress: int, "#finished": int} $sandbox
   *   The update sandbox.
   */
  protected function finish(array $sandbox): void {
    if ($sandbox['#finished'] !== 1) {
      return;
    }

    // Delete the old__* tables.
    $schema = $this->db->schema();
    foreach ($this->getAffectedTables() as $table => $field) {
      $schema->dropTable("old__$table");
    }

    // Remove references to deleted solutions.
    $this->db->query("DELETE {node_revision__solution_data}
      FROM {node_revision__solution_data}
      LEFT JOIN {node_field_revision} ON solution_data_target_revision_id = vid
      WHERE vid IS NULL");
  }

  /**
   * Returns the solution tables.
   *
   * @return iterable<string, string>
   *   The tables as key and the revision field as value.
   */
  protected function getAffectedTables(): iterable {
    // Revisions base table.
    yield 'node_revision' => 'vid';
    // Revisions data table.
    yield 'node_field_revision' => 'vid';

    // Revisions dedicated tables.
    foreach (self::DEDICATED_TABLES as $table) {
      yield "node_revision__$table" => 'revision_id';
    }
  }

}
