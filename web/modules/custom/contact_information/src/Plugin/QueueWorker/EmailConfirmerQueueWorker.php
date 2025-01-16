<?php

declare(strict_types=1);

namespace Drupal\contact_information\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process queued email confirmation requests on CRON run.
 *
 * @QueueWorker(
 *   id = "joinup_email_confirmer",
 *   title = @Translation("Delayed email confirmation request dispatcher"),
 *   cron = {"time" = 5},
 * )
 */
class EmailConfirmerQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly ContactInformationEmailConfirmInterface $emailConfirm,
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
      $container->get('contact_information.email_confirm'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->emailConfirm->confirmEmail($data['email']);
  }

}
