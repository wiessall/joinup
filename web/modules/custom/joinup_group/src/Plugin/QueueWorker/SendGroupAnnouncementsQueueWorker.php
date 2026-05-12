<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
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
 */
#[QueueWorker(
  id: 'send_group_announcements',
  title: new TranslatableMarkup('Delayed announcements request dispatcher'),
  cron: ['time' => 10],
)]
class SendGroupAnnouncementsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Static cache for message entity storage.
   */
  protected EntityStorageInterface $storage;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly JoinupMessageDeliveryInterface $messageDelivery,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly QueueFactory $queueFactory,
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
      $container->get('joinup_notification.message_delivery'),
      $container->get('entity_type.manager'),
      $container->get('queue')
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

    // Entry created in AnnouncementTransitionSubscriber.
    /* @see \Drupal\joinup_group\EventSubscriber\AnnouncementTransitionSubscriber::onAnnouncementApproved() */
    if (!isset($data['recipients'])) {
      try {
        // Requeue this announcement after fetching the recipients.
        $queue = $this->queueFactory->get('send_group_announcements');
        $queue->createQueue();
        $offset = 0;
        $limit = 250;
        // Don't allow queue entries with tens of thousands of recipients.
        while ($recipients = $announcement->getRecipients($offset, $limit)) {
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
    // 250 user IDs, so it's safe to deliver the announcement.
    if (!empty($data['recipients'])) {
      $accounts = $this->entityTypeManager->getStorage('user')->loadMultiple($data['recipients']);
      $this->messageDelivery->sendMessageToMultipleUsers($announcement, $accounts);
    }
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
