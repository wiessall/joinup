<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Drush\Commands;

use Drupal\sti_rdf\Service\GraphSynchronizer;
use Drupal\sti_rdf\Service\SyncImporter;
use Drupal\sti_rdf\Service\UriReminter;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Inspects and repairs STI instance URIs.
 *
 * The update hook sti_rdf_update_11001() only switches bundle config. //? update hook wrong; this isn't even shipped yet. let's stick with delivering a solid and fully understood v0.1 at the end of the review
 * Rewriting the URIs already stored is uri-remint's job, run in a
 * maintenance window together with a graph rebuild, because rdf_sync
 * replaces triples by the URI it has stored and cannot find triples written
 * under an old one.
 */
final class StiRdfUriCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'sti_rdf.uri_reminter')]
    private readonly UriReminter $reminter,
    #[Autowire(service: 'sti_rdf.sync_importer')]
    private readonly SyncImporter $syncImporter,
    #[Autowire(service: 'sti_rdf.graph_synchronizer')]
    private readonly GraphSynchronizer $graphSynchronizer,
  ) {
    parent::__construct();
  }

  /**
   * Writes existing entities to the graph, in chunks, at a steady pace.
   *
   * For rebuilding the graph: the same result as rdf_sync:synchronize,
   * without its slowdown on large entity types. Nothing is saved. Safe to
   * stop and to repeat; --after-id resumes where a run stopped.
   */
  #[CLI\Command(name: 'sti-rdf:graph-sync')]
  #[CLI\Argument(name: 'entityTypeId', description: 'node, paragraph or taxonomy_term.')]
  #[CLI\Option(name: 'bundle', description: 'Comma-separated bundles; all mapped bundles when omitted.')]
  #[CLI\Option(name: 'after-id', description: 'Resume after this entity id.')]
  #[CLI\Option(name: 'chunk', description: 'Entities per write.')]
  #[CLI\Usage(name: 'drush sti-rdf:graph-sync node', description: 'Writes every mapped node to the graph.')]
  #[CLI\Usage(name: 'drush sti-rdf:graph-sync paragraph --after-id=412000', description: 'Resumes the paragraphs after id 412000.')]
  public function graphSync(string $entityTypeId, array $options = ['bundle' => '', 'after-id' => 0, 'chunk' => 50]): int {
    $bundles = array_values(array_filter(array_map('trim', explode(',', (string) $options['bundle']))));
    $cursor = max(0, (int) $options['after-id']);
    $chunk = max(1, (int) $options['chunk']);

    $total = $this->graphSynchronizer->count($entityTypeId, $bundles, $cursor);
    $this->io()->writeln(sprintf('%d %s entit(ies) to go, %d per write.', $total, $entityTypeId, $chunk));

    $started = microtime(TRUE);
    $done = 0;
    $synced = 0;
    $failed = [];
    $nextReport = 0;
    do {
      $result = $this->graphSynchronizer->syncChunk($entityTypeId, $bundles, $cursor, $chunk);
      $cursor = $result['last_id'];
      $done += $result['examined'];
      $synced += $result['synced'];
      $failed += $result['failed'];
      if ($done >= $nextReport && $result['examined'] > 0) {
        $elapsed = max(0.001, microtime(TRUE) - $started);
        $this->io()->writeln(sprintf('%d of %d  |  %.0f per second  |  %s elapsed  |  last id %d  |  %d failed',
          $done, $total, $done / $elapsed, gmdate('H:i:s', (int) $elapsed), $cursor, count($failed)));
        $nextReport = $done + max(1000, $chunk);
      }
    } while ($result['examined'] > 0);

    $this->io()->writeln(sprintf('Handed to rdf_sync: %d (it leaves out unpublished ones). Not mapped: %d. Failed: %d. Last id: %d.',
      $synced, $done - $synced - count($failed), count($failed), $cursor));
    if ($failed) {
      $this->io()->section('Entities rdf_sync could not write');
      foreach (array_slice($failed, 0, 50, TRUE) as $id => $message) {
        $this->io()->writeln("  $entityTypeId $id: $message");
      }
      if (count($failed) > 50) {
        $this->io()->writeln(sprintf('  … and %d more.', count($failed) - 50));
      }
      return self::EXIT_FAILURE;
    }
    return self::EXIT_SUCCESS;
  }

  /**
   * Brings rdf_sync mappings from config/sync into the live site.
   *
   * For when a full config import is blocked by unrelated differences. Only
   * the rdf_sync mapping of config that already exists is touched. Without
   * --apply it only lists what differs.
   */
  #[CLI\Command(name: 'sti-rdf:syncs-import')]
  #[CLI\Option(name: 'apply', description: 'Write the rdf sync mappings. Without it, only the differences are listed.')]
  #[CLI\Usage(name: 'drush sti-rdf:syncs-import', description: 'Lists config whose rdf_sync mapping differs from config/sync.')]
  #[CLI\Usage(name: 'drush sti-rdf:syncs-import --apply', description: 'Writes the config/sync mappings into active configuration.')]
  public function syncsImport(array $options = ['apply' => FALSE]): int {
    $diff = $this->syncImporter->diff();

    if ($diff['missing']) {
      $this->io()->section('In config/sync with a rdf sync mapping, but not on the site (needs a real config import)');
      $this->io()->listing($diff['missing']);
    }
    if (!$diff['changed']) {
      $this->io()->success('Every rdf_sync mapping on the site matches config/sync.');
      return self::EXIT_SUCCESS;
    }

    $this->io()->section('rdf_sync mapping differs from config/sync');
    $rows = [];
    foreach ($diff['changed'] as $name => $change) {
      $rows[] = [$name, self::summarise($change['from']), self::summarise($change['to'])];
    }
    $this->io()->table(['Config', 'On the site', 'In config/sync'], $rows);

    if (!$options['apply']) {
      $this->io()->note('Nothing was changed. Run again with --apply to write these rdf_sync mappings.');
      return self::EXIT_FAILURE;
    }
    $names = $this->syncImporter->apply();
    $this->logger()->success(dt('Imported the rdf_sync mapping of @count config object(s). Rebuild caches (drush cr) before syncing.', ['@count' => count($names)]));
    return self::EXIT_SUCCESS;
  }

  /**
   * One line describing a rdf_sync mapping: its type, or its predicates.
   */
  private static function summarise(mixed $rdf_sync_mapping): string {
    if (!is_array($rdf_sync_mapping)) {
      return '(none)';
    }
    $parts = [];
    if (isset($rdf_sync_mapping['type'])) {
      $parts[] = 'type ' . $rdf_sync_mapping['type'];
    }
    array_walk_recursive($rdf_sync_mapping, static function ($value, $key) use (&$parts): void {
      if ($key === 'predicate' && is_string($value)) {
        $parts[] = $value;
      }
    });
    $shown = array_slice($parts, 0, 3);
    return implode("\n", $shown) . (count($parts) > 3 ? "\n… +" . (count($parts) - 3) : '');
  }

  /**
   * Shows pending URI plugin changes and legacy URIs still stored.
   */
  #[CLI\Command(name: 'sti-rdf:uri-report')]
  #[CLI\Option(name: 'legacy-prefix', description: 'Comma-separated further URI prefixes to count as pre-P0, e.g. http://example.ddev.local/')]
  #[CLI\Usage(name: 'drush sti-rdf:uri-report', description: 'Lists bundles still on a legacy URI plugin, stored URI hosts, and legacy URIs per bundle.')]
  public function report(array $options = ['legacy-prefix' => '']): int {
    $this->reminter->setExtraLegacyPrefixes(explode(',', (string) $options['legacy-prefix']));
    $plan = $this->reminter->planConfigChanges();
    if ($plan) {
      $this->io()->section('Bundles still on a legacy URI plugin');
      $rows = [];
      foreach ($plan as $change) {
        $rows[] = [$change['entity_type'], $change['bundle'], $change['from'] ?? '(none)', $change['to']];
      }
      $this->io()->table(['Entity type', 'Bundle', 'From', 'To'], $rows);
    }
    else {
      $this->io()->success('Every mapped STI bundle uses an sti_namespace derivative.');
    }

    // Every host that is neither sti-portal.fao.org over https nor an
    // external authority is a prefix uri-remint has to be told about.
    $this->io()->section('Stored URIs of typed bundles, by host');
    $rows = [];
    foreach ($this->reminter->storedHosts() as $host => $count) {
      $rows[] = [$host, $count];
    }
    $this->io()->table(['Entity type and host', 'Count'], $rows);

    $left = $this->reminter->verify();
    if ($left) {
      $this->io()->section('Legacy URIs still stored');
      $rows = [];
      foreach ($left as $scope => $count) {
        $rows[] = [$scope, $count];
      }
      $this->io()->table(['Scope', 'Count'], $rows);
      return self::EXIT_FAILURE;
    }
    $this->io()->success('No legacy URIs are stored for typed bundles.');
    return $plan ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

  /**
   * Switches bundles to typed URI plugins and rewrites legacy URIs.
   *
   * Irreversible once the new URIs are published.
   * Afterwards, drop the graph and run rdf_sync:synchronize for node,
   * paragraph and taxonomy_term: rdf_sync deletes triples by the URI it
   * currently knows, so it cannot clean up triples written under old URIs.
   */
  #[CLI\Command(name: 'sti-rdf:uri-remint')]
  #[CLI\Option(name: 'batch-size', description: 'Entities examined per database round.')]
  #[CLI\Option(name: 'legacy-prefix', description: 'Comma-separated further URI prefixes to re-mint, e.g. http://example.ddev.local/')]
  #[CLI\Usage(name: 'drush sti-rdf:uri-remint', description: 'Rewrites every legacy URI to https://sti-portal.fao.org/id/{type}/{uuid}.')]
  public function remint(array $options = ['batch-size' => 500, 'legacy-prefix' => '']): int {
    $this->reminter->setExtraLegacyPrefixes(explode(',', (string) $options['legacy-prefix']));
    $changed = $this->reminter->applyConfigChanges();
    $this->logger()->notice(dt('Switched @count bundle(s) to typed URI plugins.', ['@count' => $changed]));

    $batchSize = max(1, (int) $options['batch-size']);
    foreach ($this->reminter->scopes() as $scope) {
      $label = $scope['entity_type'] . ':' . $scope['bundle'] . ($scope['field'] ? ':' . $scope['field'] : '');
      $cursor = 0;
      $rewritten = 0;
      do {
        $result = $this->reminter->remintBatch($scope, $cursor, $batchSize);
        $cursor = $result['last_id'];
        $rewritten += $result['rewritten'];
      } while ($result['examined'] > 0);
      if ($rewritten > 0) {
        $this->logger()->notice(dt('@scope: rewrote @count URI(s).', ['@scope' => $label, '@count' => $rewritten]));
      }
    }

    $left = $this->reminter->verify();
    if ($left) {
      $this->logger()->error(dt('Legacy URIs remain after the rewrite: @left', ['@left' => json_encode($left)]));
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(dt('All typed bundles now use https://sti-portal.fao.org/id/ URIs. Drop the graph and re-run rdf_sync:synchronize.'));
    return self::EXIT_SUCCESS;
  }

}
