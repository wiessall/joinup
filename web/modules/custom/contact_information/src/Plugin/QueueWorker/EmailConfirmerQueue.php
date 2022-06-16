<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process queued email confirmation requests on CRON run.
 *
 * @QueueWorker(
 *   id = "joinup_email_confirmer",
 *   title = @Translation("Delayed email confirmation request dispatcher"),
 *   cron = {"time" = 5}
 * )
 */
class EmailConfirmerQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    \Drupal::service('email_confirmer')->confirm($data);
  }

}
