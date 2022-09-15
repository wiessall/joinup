<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends queued announcements by email.
 *
 * @QueueWorker(
 *   id = "send_group_announcements",
 *   title = @Translation("Delayed announcements request dispatcher"),
 *   cron = {"time" = 10}
 * )
 */
class SendGroupAnnouncementsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The message delivery service.
   */
  protected JoinupMessageDeliveryInterface $messageDelivery;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\joinup_notification\JoinupMessageDeliveryInterface $message_delivery
   *   The message delivery service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, JoinupMessageDeliveryInterface $message_delivery, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messageDelivery = $message_delivery;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('joinup_notification.message_delivery'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    /** @var \Drupal\joinup_group\Entity\AnnouncementInterface $announcement */
    $announcement = $this->entityTypeManager->getStorage('message')->load($data['announcement']);
    // The entity might have been deleted in the meantime.
    if ($announcement) {
      $this->messageDelivery->sendMessageToEmailAddresses($announcement, $data['emails']);
    }
  }

}
