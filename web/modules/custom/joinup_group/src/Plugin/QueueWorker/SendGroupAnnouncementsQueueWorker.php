<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends queued announcements by email.
 *
 * In order to ensure the queue reliability and to avoid abusing the resources,
 * this queue worker is processing two kind of entries:
 * - Entries created by AnnouncementsSubscriber::onAnnouncementApproved(), which
 *   only contain the announcement ID. Having the announcement ID, the worker is
 *   fetching the recipients in batches of 250 emails and requeue each batch in
 *   the same queue.
 * - Entries created by this worker after the recipients are fetched, which
 *   contain the announcement ID and up to 250 emails. This process is actually
 *   delivering the announcement.
 * This complexity is required as we need offload the transition subscriber for
 * doing intensive operations for groups with a huge number of members.
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
   * The queue factory service.
   */
  protected QueueFactory $queueFactory;

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * Static cache for message entity storage.
   */
  protected EntityStorageInterface $storage;

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
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, JoinupMessageDeliveryInterface $message_delivery, EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messageDelivery = $message_delivery;
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->connection = $connection;
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
      $container->get('queue'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    /** @var \Drupal\joinup_group\Entity\AnnouncementInterface $announcement */
    if (!$announcement = $this->getMessageStorage()->load($data['announcement'])) {
      // The entity might have been deleted in the meantime.
      return;
    }

    // Entry created in AnnouncementsSubscriber::onAnnouncementApproved().
    // @see \Drupal\joinup_group\EventSubscriber\AnnouncementsSubscriber::onAnnouncementApproved()
    if (!isset($data['recipients'])) {
      try {
        // Requeue this announcement after fetching the recipients.
        $queue = $this->queueFactory->get('send_group_announcements');
        $queue->createQueue();
        $offset = 0;
        $limit = 250;
        // Don't allow queue entries with tens of thousands of recipients.
        while ($recipients = $this->getGroupMembersEmails($announcement->getGroup(), $offset, $limit)) {
          $queue->createItem([
            'announcement' => (int) $announcement->id(),
            'recipients' => $recipients,
          ]);
          $offset += $limit;
        }
      }
      catch (MissingGroupException $exception) {
        // Something bad happened with the announcement group since this queue
        // entry has been created. Fail silently and jump to the next entry.
      }
      return;
    }

    // This is a queue entry with recipients already fetched and having up to
    // 250 emails, so it's safe to deliver the announcement.
    if (!empty($data['recipients'])) {
      $this->messageDelivery->sendMessageToEmailAddresses($announcement, $data['recipients']);
    }
  }

  /**
   * Returns a list of emails belonging to the members of a given group.
   *
   * Note that this method is not using the entity API as some groups may have
   * tens of thousands of members, and we need a fast way to queue them. Doing a
   * direct database query is faster.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   * @param int $offset
   *   The query offset.
   * @param int $limit
   *   The query limit.
   *
   * @return string[]
   *   A list of groups members emails.
   */
  protected function getGroupMembersEmails(GroupInterface $group, int $offset, int $limit): array {
    $query = $this->connection->select('og_membership', 'og');
    $query->join('users_field_data', 'u', 'og.uid = u.uid');
    return $query->condition('og.entity_type', 'rdf_entity')
      ->fields('u', ['mail'])
      ->condition('og.entity_bundle', JoinupGroupHelper::GROUP_BUNDLES, 'IN')
      ->condition('og.entity_id', $group->id())
      ->condition('og.state', OgMembershipInterface::STATE_ACTIVE)
      ->condition('u.status', 1)
      ->orderBy('og.uid')
      ->range($offset, $limit)
      ->execute()
      ->fetchCol();
  }

  /**
   * Returns the message entity type storage.
   *
   * Since the worker processor is called many times in a single request, it
   * makes sense to statically cache the message entity type storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The message entity type storage.
   */
  protected function getMessageStorage(): EntityStorageInterface {
    if (!isset($this->storage)) {
      $this->storage = $this->entityTypeManager->getStorage('message');
    }
    return $this->storage;
  }

}
