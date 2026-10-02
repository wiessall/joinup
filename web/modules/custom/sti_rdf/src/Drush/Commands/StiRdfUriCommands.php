<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Drush\Commands;

use Drupal\sti_rdf\Service\UriReminter;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Inspects and repairs STI instance URIs.
 *
 * The update hook sti_rdf_update_11001() only switches bundle config.
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
  ) {
    parent::__construct();
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
