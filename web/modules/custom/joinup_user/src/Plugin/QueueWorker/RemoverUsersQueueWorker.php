<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_user\JoinupUserDeletedInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process queued users to remove on CRON run.
 */
#[QueueWorker(
  id: 'joinup_removed_users',
  title: new TranslatableMarkup('Delete one user'),
  cron: ['time' => 30],
)]
class RemoverUsersQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JoinupUserDeletedInterface $userDeleted,
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
      $container->get('joinup_user.deleted'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!empty($data['uid'])) {
      $account = $this->entityTypeManager->getStorage('user')->load($data['uid']);
    }
    elseif (!empty($data['name'])) {
      $account = user_load_by_name($data['name']);
    }
    else {
      return;
    }

    // Make sure account wasn't deleted in the meantime.
    if ($account instanceof UserInterface) {
      user_cancel(['user_cancel_notify' => TRUE], $account->id(), 'user_cancel_delete');
      // Process batch correctly based on execution environment.
      if (PHP_SAPI === 'cli' && function_exists('drush_backend_batch_process')) {
        // Process batch using Drush.
        drush_backend_batch_process();
      }
      else {
        // Process batch using Drupal's batch processing.
        batch_process();
      }

      // Commit changes.
      $this->userDeleted->destruct();
    }

  }

}
