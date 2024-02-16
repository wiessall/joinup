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

  /**
   * The contact information email confirm service.
   */
  protected ContactInformationEmailConfirmInterface $emailConfirm;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\contact_information\ContactInformationEmailConfirmInterface $email_confirm
   *   The contact information email confirm service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContactInformationEmailConfirmInterface $email_confirm) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->emailConfirm = $email_confirm;
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
