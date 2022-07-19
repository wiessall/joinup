<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\email_confirmer\EmailConfirmerManagerInterface;
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
   * The email confirmer.
   */
  protected EmailConfirmerManagerInterface $emailConfirmer;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\email_confirmer\EmailConfirmerManagerInterface $email_confirmer
   *   The email confirmer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EmailConfirmerManagerInterface $email_confirmer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->emailConfirmer = $email_confirmer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('email_confirmer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->emailConfirmer->confirm($data['email'], $data['data']);
  }

}
