<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\Claims\ResolutionReconciler;
use Drupal\sti_dedupe\ConflictQueue;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\ReplacementStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes claim conflicts on innovations whose sources changed.
 *
 * Items are queued, not processed inline, because the trigger is usually a
 * migration saving hundreds of solutions: one refresh per affected
 * innovation on cron is cheap; one per save during import is not.
 *
 * Item shapes:
 * - ['nids' => int[]]: refresh these innovations.
 * - ['all' => TRUE]: a mapping or the exclusivity rule changed, so every
 *   innovation may be affected; fan out into 'nids' items.
 */
#[QueueWorker(
  id: ConflictRefreshWorker::QUEUE,
  title: new TranslatableMarkup('Refresh ATIO claim conflicts'),
  cron: ['time' => 60],
)]
final class ConflictRefreshWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public const QUEUE = 'sti_dedupe_conflict_refresh';

  /**
   * Innovations per fanned-out item.
   */
  private const CHUNK = 50;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ResolutionReconciler $reconciler,
    private readonly ReplacementStore $replacements,
    private readonly QueueFactory $queueFactory,
    private readonly ConflictQueue $conflictQueue,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('sti_dedupe.resolution_reconciler'),
      $container->get('sti_dedupe.replacements'),
      $container->get('queue'),
      $container->get('sti_dedupe.conflict_queue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!empty($data['all'])) {
      $this->fanOut();
      return;
    }

    $nids = array_map('intval', (array) ($data['nids'] ?? []));
    $nids = array_values(array_diff($nids, $this->replacements->replacedAmong($nids)));
    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($storage->loadMultiple($nids) as $innovation) {
      if ($innovation instanceof NodeInterface && $innovation->bundle() === ContentModel::INNOVATION) {
        // Exceptions propagate: the queue keeps the item and retries it, and
        // the reconciler has already logged an invariant failure at error.
        $this->reconciler->refresh($innovation);
      }
    }
    $storage->resetCache($nids);
  }

  /**
   * Queues every live innovation, in chunks.
   */
  private function fanOut(): void {
    $this->conflictQueue->fullRefreshStarted();
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ContentModel::INNOVATION)
      ->sort('nid')
      ->execute();
    $queue = $this->queueFactory->get(self::QUEUE);
    foreach (array_chunk(array_map('intval', array_values($ids)), self::CHUNK) as $chunk) {
      $queue->createItem(['nids' => $chunk]);
    }
  }

}
