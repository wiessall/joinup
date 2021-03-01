<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a worker to consume the 'joinup_group:group_update' queue.
 *
 * @QueueWorker(
 *   id = "joinup_group:group_update",
 *   title = @Translation("Push data about entities to be updated in 'joinup_group:group_content_update' queue"),
 *   cron = {
 *     "time" = 10,
 *   },
 * ),
 */
class JoinupGroupQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
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
      ->getStorage('rdf_entity')
      ->load($data['entity_id']);

    if (!$group) {
      // The group might have been deleted in the meantime.
      return;
    }

    /** @var \Drupal\joinup_group\Queue\JoinupGroupQueue $group_content_queue */
    $group_content_queue = $this->queueFactory->get('joinup_group:group_content_update');

    // Push group content entities into joinup_group:group_content_update queue.
    foreach ($group->getGroupContentIds() as $entity_type_id => $entity_ids) {
      foreach ($entity_ids as $entity_id) {
        $group_content_queue->createItem([
          'group_id' => $data['entity_id'],
          'entity_type_id' => $entity_type_id,
          'entity_id' => $entity_id,
        ]);
      }
    }
  }

}
