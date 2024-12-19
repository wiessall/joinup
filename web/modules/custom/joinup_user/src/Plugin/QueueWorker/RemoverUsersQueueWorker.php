<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\QueueWorker;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
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
   * The comment storage class.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $commentStorage;

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly LoggerInterface $logger,
    protected readonly ImmutableConfig $userSettings,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->commentStorage = $this->entityTypeManager->getStorage('comment');
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
      $container->get('module_handler'),
      $container->get('logger.factory')->get('user'),
      $container->get('config.factory')->get('user.settings'),
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
    flag_user_cancel([], $user, 'user_cancel_reassign');
    joinup_invite_user_cancel([], $user, 'user_cancel_reassign');

    // Anonymize comments.
    $comments = $this->commentStorage->loadByProperties(['uid' => $user->id()]);
    foreach ($comments as $comment) {
      $comment->original = clone $comment;
      $comment->joinupUserCancelReassign = TRUE;
      $comment->skip_notification = TRUE;
      foreach (array_keys($comment->getTranslationLanguages()) as $langcode) {
        $commentTranslated = $comment->getTranslation($langcode);
        $commentTranslated->setOwnerId(0);
        $commentTranslated->setAuthorName($this->userSettings->get('anonymous'));
      }
      $comment->save();
    }

    // Anonymize nodes for this old account.
    $nodeRevisions = $this->nodeStorage->userRevisionIds($user);
    foreach ($nodeRevisions as $vid) {
      $node = $this->nodeStorage->loadRevision($vid);
      $node->joinupUserCancelReassign = TRUE;
      $node->skip_notification = TRUE;

      _node_mass_update_helper(
        $node,
        ['uid' => 0, 'revision_uid' => 0]
      );
    }

    // Delete account.
    $user->delete();
    $this->logger->notice(
      'Deleted user: %id %name',
      ['%id' => $user->id(), '%name' => $user->getAccountName()]
    );
  }

}
