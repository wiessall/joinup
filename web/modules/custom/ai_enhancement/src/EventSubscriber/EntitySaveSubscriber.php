<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\EventSubscriber;

use Drupal\ai_enhancement\Entity\EnhancementProfileInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Triggers enhancement jobs on entity insert/update.
 *
 * For each matching profile (same entity type + bundle), enqueue one job.
 * Multiple profiles per bundle are allowed — useful for running parallel
 * enhancements with different prompts/models.
 *
 * Despite the "EventSubscriber" namespace, this isn't a Symfony event
 * subscriber — it's invoked directly from .module hook implementations.
 * The directory name is kept for discoverability with future event-based
 * trigger sources (e.g. custom revision-published events).
 */
class EntitySaveSubscriber {

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Called from hook_entity_insert / hook_entity_update.
   */
  public function onEntitySave(FieldableEntityInterface $entity): void {
    $entityType = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $profiles = $this->entityTypeManager
      ->getStorage('enhancement_profile')
      ->loadByProperties([
        'entity_type' => $entityType,
        'bundle' => $bundle,
      ]);

    if (empty($profiles)) {
      return;
    }

    $queue = $this->queueFactory->get('ai_enhancement_worker');
    foreach ($profiles as $profile) {
      assert($profile instanceof EnhancementProfileInterface);
      $queue->createItem([
        'entity_type' => $entityType,
        'entity_id' => $entity->id(),
        'profile_id' => $profile->id(),
      ]);
    }
  }

}
