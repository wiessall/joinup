<?php

declare(strict_types = 1);

namespace Drupal\joinup_user\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process queued users to remove on CRON run.
 *
 * @QueueWorker(
 *   id = "joinup_removed_users",
 *   title = @Translation("Remove Users"),
 *   cron = {"time" = 30},
 * )
 */
class RemoverUsersQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    foreach ($data as $userId) {
      // The third argument can be one of the following:
      //   - user_cancel_block: disable user, leave content
      //   - user_cancel_block_unpublish: disable user, unpublish content
      //   - user_cancel_reassign: delete user, reassign content to uid=0
      //   - user_cancel_delete: delete user, delete content
      user_cancel([], $userId, 'user_cancel_reassign');
      drush_backend_batch_process();
    }
  }

}
