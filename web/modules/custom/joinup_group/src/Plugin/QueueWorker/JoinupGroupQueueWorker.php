<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a worker to consume the 'joinup_group:group_update' queue.
 */
#[QueueWorker(
  id: 'joinup_group:group_update',
  title: new TranslatableMarkup('Push data about entities to be updated in "joinup_group:group_content_update" queue'),
  cron: ['time' => 10],
)]
class JoinupGroupQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityTypeBundleInfoInterface $bundleInfo,
    protected readonly QueueFactory $queueFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $group = $this->entityTypeManager
      ->getStorage('node')
      ->load($data['entity_id']);

    if (!$group) {
      // The group might have been deleted in the meantime.
      return;
    }

    // Push group content entities into joinup_group:group_content_update queue.
    $group_content_ids = $group->getGroupContentIds();
    foreach ($group_content_ids as $entity_type_id => $bundles) {
      $bundle_info = $this->bundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle => $entity_ids) {
        $bundle_class = $bundle_info[$bundle]['class'] ?? NULL;
        // Group content that is also group itself has higher priority, because
        // their group content aliases depend on the their alias. For example a
        // solution event alias is prefixed by the solution alias, so the
        // solution alias must come first.
        if (is_subclass_of($bundle_class, GroupInterface::class)) {
          $this->createQueueItems($data['entity_id'], $entity_type_id, $entity_ids);
          // The subgroup is queued, remove it from the list.
          $group_content_ids[$entity_type_id][$bundle];
        }
      }
      // After removing subgroups, this entity type IDs list may became empty.
      array_filter($group_content_ids[$entity_type_id]);
    }

    // Now that subgroups were queued, it's safe to queue the rest of content.
    foreach ($group_content_ids as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle => $entity_ids) {
        $this->createQueueItems($data['entity_id'], $entity_type_id, $entity_ids);
      }
    }
  }

  /**
   * Creates queue items given a list of entity IDs.
   *
   * @param string $group_id
   *   The group ID.
   * @param string $entity_type_id
   *   The type of entities to be queued.
   * @param array $entity_ids
   *   A list of entity IDs.
   */
  protected function createQueueItems(string $group_id, string $entity_type_id, array $entity_ids): void {
    $queue = $this->queueFactory->get('joinup_group:group_content_update');
    foreach ($entity_ids as $entity_id) {
      $queue->createItem([
        'group_id' => $group_id,
        'entity_type_id' => $entity_type_id,
        'entity_id' => $entity_id,
      ]);
    }
  }

}
