<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Builds batch operations for scheduling translation of a collection hierarchy.
 */
class TranslationScheduler {

  use AutowireTrait;
  use StringTranslationTrait;

  const string STATE_KEY = 'joinup_i18n.scheduled_collections';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
  ) {}

  /**
   * Builds a complete batch definition for scheduling a collection.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The collection node.
   *
   * @return array
   *   A batch definition array for batch_set().
   */
  public function buildBatch(CollectionInterface $collection): array {
    return [
      'operations' => array_merge(
        [[[static::class, 'storeCollectionId'], [(string) $collection->id()]]],
        $this->buildBatchOperations($collection),
      ),
      'finished' => [static::class, 'batchFinished'],
      'title' => $this->t('Scheduling translation'),
      'progress_message' => $this->t('Processing batch @current of @total.'),
    ];
  }

  /**
   * Stores the collection ID in batch context for the finished callback.
   *
   * @param string $collectionId
   *   The collection node ID.
   * @param array $context
   *   The batch context.
   */
  public static function storeCollectionId(string $collectionId, array &$context): void {
    $context['results']['collection_id'] = $collectionId;
  }

  /**
   * Builds batch operations for a collection and all its descendant content.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The collection node.
   *
   * @return array
   *   An array of [callable, args] pairs for use with batch_set().
   */
  protected function buildBatchOperations(CollectionInterface $collection): array {
    $idsByBundle = $collection->getGroupContentIds()['node'] ?? [];
    if (!empty($idsByBundle['solution'])) {
      // Solutions should take precedence over other content types to ensure
      // correct processing of path aliases.
      $solutionIds = $idsByBundle['solution'];
      unset($idsByBundle['solution']);
      $idsByBundle = ['solution' => $solutionIds] + $idsByBundle;
    }
    $ids = array_reduce([...$idsByBundle], fn(array $ids, array $bundleIds) => [...$ids, ...$bundleIds], []);

    array_unshift($ids, (int) $collection->id());

    return array_map(
      fn(array $chunk) => [[static::class, 'processBatchChunk'], [$chunk]],
      array_chunk($ids, 20),
    );
  }

  /**
   * Batch operation callback: processes a chunk of node IDs.
   *
   * @param string[] $ids
   *   Node IDs to process.
   * @param array $context
   *   The batch context.
   */
  public static function processBatchChunk(array $ids, array &$context): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $translationHelper = \Drupal::service(TranslationHelper::class);
    foreach ($storage->loadMultiple($ids) as $entity) {
      assert($entity instanceof NodeInterface);
      if (!$entity->isPublished()) {
        continue;
      }
      if ($entity instanceof ArchivableEntityInterface && $entity->isArchived()) {
        continue;
      }

      if ($translationHelper->hasTranslatableFields($entity)) {
        tmgmt_content_create_continuous_job_items($entity);
      }

      if ($entity instanceof GroupInterface) {
        $settings = $entity->get("{$entity->bundle()}_settings")->entity;
        if ($settings && $translationHelper->hasTranslatableFields($settings)) {
          // Add also the corresponding group settings.
          tmgmt_content_create_continuous_job_items($settings);
        }

        if ($entity instanceof SolutionInterface) {
          $eifPerspective = $entity->get('eif_perspective')->entity;
          if ($eifPerspective && $translationHelper->hasTranslatableFields($eifPerspective)) {
            // Add also the corresponding EIF perspective.
            tmgmt_content_create_continuous_job_items($eifPerspective);
          }
        }
      }

      $context['results']['count'] = ($context['results']['count'] ?? 0) + 1;
    }
  }

  /**
   * Records the current time as the sent timestamp for a collection.
   *
   * @param string $collectionId
   *   The collection node ID.
   */
  protected static function markAsSent(string $collectionId): void {
    $state = \Drupal::state();
    $sent = $state->get(self::STATE_KEY, []);
    $sent[$collectionId] = \Drupal::time()->getRequestTime();
    $state->set(self::STATE_KEY, $sent);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed without errors.
   * @param array $results
   *   Results collected from batch operations.
   * @param array $operations
   *   Unprocessed operations, if any.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      static::markAsSent($results['collection_id']);
      \Drupal::messenger()
        ->addStatus(t('Translation scheduled: @count item(s) queued.', [
          '@count' => $results['count'] ?? 0,
        ]));
    }
    else {
      \Drupal::messenger()
        ->addError(t('An error occurred while scheduling translation.'));
    }
  }

  /**
   * Returns the timestamp when a collection was last scheduled for translation.
   *
   * @param string $collectionId
   *   The collection node ID.
   *
   * @return int|null
   *   Unix timestamp, or null if never sent.
   */
  public function getSentTimestamp(string $collectionId): ?int {
    $sent = $this->state->get(self::STATE_KEY, []);
    return $sent[$collectionId] ?? NULL;
  }

}
