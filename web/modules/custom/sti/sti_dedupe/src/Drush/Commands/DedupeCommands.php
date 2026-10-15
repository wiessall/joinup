<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\DuplicateFinder;
use Drupal\sti_dedupe\ReplacementStore;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commands for duplicate candidates and claim conflicts.
 */
final class DedupeCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'sti_dedupe.finder')]
    private readonly DuplicateFinder $finder,
    #[Autowire(service: 'sti_dedupe.candidates')]
    private readonly CandidateStore $candidates,
    #[Autowire(service: 'sti_dedupe.replacements')]
    private readonly ReplacementStore $replacements,
  ) {
    parent::__construct();
  }

  /**
   * Scans a collection's solutions for possible duplicates.
   *
   * Run after a migration. Candidates are precomputed so that the review
   * queue is a cheap query rather than a search per row per page load.
   */
  #[CLI\Command(name: 'sti-dedupe:scan', aliases: ['stidd'])]
  #[CLI\Argument(name: 'collection', description: "The collection's short_id or node id.")]
  #[CLI\Option(name: 'since', description: 'Only scan solutions created at or after this UNIX timestamp.')]
  #[CLI\Option(name: 'limit', description: 'Stop after this many solutions.')]
  #[CLI\Usage(name: 'drush sti-dedupe:scan wocat', description: 'Scans every solution in the WOCAT collection.')]
  public function scan(string $collection, array $options = ['since' => NULL, 'limit' => NULL]): int {
    $collectionNode = $this->loadCollection($collection);
    if (!$collectionNode) {
      $this->logger()->error(dt('No collection matched "!id".', ['!id' => $collection]));
      return self::EXIT_FAILURE;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ContentModel::SOLUTION)
      ->condition('og_audience', $collectionNode->id())
      ->sort('nid');
    if ($options['since'] !== NULL) {
      $query->condition('created', (int) $options['since'], '>=');
    }
    if ($options['limit'] !== NULL) {
      $query->range(0, (int) $options['limit']);
    }

    $ids = array_values($query->execute());
    $total = count($ids);
    $withCandidates = 0;
    $scanned = 0;
    foreach (array_chunk($ids, 50) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $solution) {
        if (!$solution instanceof NodeInterface) {
          continue;
        }
        $matches = $this->finder->findCandidates($solution);
        $this->candidates->replaceForSource((int) $solution->id(), (int) $collectionNode->id(), $matches);
        $withCandidates += $matches ? 1 : 0;
        $scanned++;
      }
      $storage->resetCache($chunk);
      $this->logger()->notice(dt('  !scanned / !total scanned, !found with candidates.', [
        '!scanned' => $scanned,
        '!total' => $total,
        '!found' => $withCandidates,
      ]));
    }

    $this->logger()->success(dt('!found of !total solutions have at least one candidate above the threshold.', [
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
      if ($node instanceof NodeInterface && $node->bundle() === ContentModel::COLLECTION) {
        return $node;
      }
    }
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ContentModel::COLLECTION)
      ->condition('short_id', $identifier)
      ->range(0, 1)
      ->execute();
    $node = $ids ? $storage->load(reset($ids)) : NULL;
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
