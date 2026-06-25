<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    // Subgroups come first so their alias is regenerated before the content
    // whose alias is prefixed by it.
    foreach ($group->getGroupContentIdsInDependencyOrder() as $entity_type_id => $bundles) {
      foreach ($bundles as $entity_ids) {
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
