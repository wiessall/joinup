<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\DuplicateFinder;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for duplicate detection.
 */
final class DedupeCommands extends DrushCommands {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DuplicateFinder $finder,
    private readonly CandidateStore $candidates,
  ) {
    parent::__construct();
  }

  /**
   * Scans a collection's solutions for possible duplicates.
   *
   * Run this after a migration. Candidates are precomputed into a table so the
   * review queue is a cheap query rather than a search per row per page load.
   *
   * @param string $collection
   *   The collection's short_id, or its node id.
   *
   * @option since
   *   Only scan solutions created at or after this UNIX timestamp, so a
   *   re-scan after an incremental import does not redo the whole collection.
   * @option limit
   *   Stop after this many solutions.
   *
   * @command sti-dedupe:scan
   * @aliases stidd
   * @usage drush sti-dedupe:scan wocat
   *   Scans every solution in the WOCAT collection.
   * @usage drush sti-dedupe:scan wocat --since=1758585600
   *   Scans only solutions created since that timestamp.
   */
  public function scan(string $collection, array $options = ['since' => NULL, 'limit' => NULL]): int {
    $collectionNode = $this->loadCollection($collection);
    if (!$collectionNode) {
      $this->logger()->error(dt('No collection matched "!id".', ['!id' => $collection]));
      return self::EXIT_FAILURE;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'solution')
      ->condition('og_audience', $collectionNode->id())
      ->sort('nid');

    if ($options['since'] !== NULL) {
      $query->condition('created', (int) $options['since'], '>=');
    }
    if ($options['limit'] !== NULL) {
      $query->range(0, (int) $options['limit']);
    }

    $ids = $query->execute();
    $total = count($ids);
    if ($total === 0) {
      $this->logger()->success(dt('No solutions to scan in !collection.', ['!collection' => $collectionNode->label()]));
      return self::EXIT_SUCCESS;
    }

    $this->logger()->notice(dt('Scanning !total solutions in !collection.', [
      '!total' => $total,
      '!collection' => $collectionNode->label(),
    ]));

    $withCandidates = 0;
    $scanned = 0;

    foreach (array_chunk($ids, 50) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $solution) {
        if (!$solution instanceof NodeInterface) {
          continue;
        }
        $matches = $this->finder->findCandidates($solution);
        $this->candidates->replaceForSource(
          (int) $solution->id(),
          (int) $collectionNode->id(),
          $matches
        );
        if ($matches) {
          $withCandidates++;
        }
        $scanned++;
      }
      $this->logger()->notice(dt('  !scanned / !total scanned, !found with candidates.', [
        '!scanned' => $scanned,
        '!total' => $total,
        '!found' => $withCandidates,
      ]));
      $storage->resetCache($chunk);
    }

    $this->logger()->success(dt('Done. !found of !total solutions have at least one candidate above the threshold.', [
      '!found' => $withCandidates,
      '!total' => $total,
    ]));

    return self::EXIT_SUCCESS;
  }

  /**
   * Loads a collection by short_id or node id.
   */
  private function loadCollection(string $identifier): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');

    if (ctype_digit($identifier)) {
      $node = $storage->load((int) $identifier);
      if ($node instanceof NodeInterface && $node->bundle() === 'collection') {
        return $node;
      }
    }

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'collection')
      ->condition('short_id', $identifier)
      ->range(0, 1)
      ->execute();

    if (!$ids) {
      return NULL;
    }
    $node = $storage->load(reset($ids));
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
