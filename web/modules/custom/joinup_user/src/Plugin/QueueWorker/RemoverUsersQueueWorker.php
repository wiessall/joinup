<?php

declare(strict_types = 1);

namespace Drupal\joinup_user\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
   * The node storage class.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);

    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
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
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($data);

    // Load required node functions.
    $this->moduleHandler->loadInclude('node', 'inc', 'node.admin');

    foreach ($users as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }

      $this->processCancellation($user);
    }
  }

  /**
   * It processes cancellation of user account.
   *
   * @param \Drupal\user\UserInterface $user
   *   User account.
   */
  protected function processCancellation(UserInterface $user): void {
    // Replicate 'user_cancel_reassign' process.
    comment_user_cancel([], $user, 'user_cancel_reassign');
    flag_user_cancel([], $user, 'user_cancel_reassign');
    joinup_invite_user_cancel([], $user, 'user_cancel_reassign');

    // Anonymize nodes for this old account.
    $nodeRevisions = $this->nodeStorage->userRevisionIds($user);
    foreach ($nodeRevisions as $vid) {
      $node = $this->nodeStorage->loadRevision($vid);
      $node->joinupUserCancelReassign = TRUE;

      _node_mass_update_helper(
        $node,
        ['uid' => 0, 'revision_uid' => 0]
      );
    }

    // Delete account.
    $user->delete();
  }

}
