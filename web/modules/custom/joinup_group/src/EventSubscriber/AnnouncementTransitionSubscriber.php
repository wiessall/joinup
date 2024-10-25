<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to group announcements transitions.
 */
class AnnouncementTransitionSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mail manager service.
   */
  protected MailManagerInterface $mailManager;

  /**
   * The language manager service.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The Portal message delivery service.
   */
  protected JoinupMessageDeliveryInterface $messageDelivery;

  /**
   * The queue factory service.
   */
  protected QueueFactory $queueFactory;

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\joinup_notification\JoinupMessageDeliveryInterface $message_delivery
   *   The Portal message delivery service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager, JoinupMessageDeliveryInterface $message_delivery, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->messageDelivery = $message_delivery;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'announcement.send.post_transition' => 'onAnnouncementSentForApproval',
      'announcement.approve.post_transition' => 'onAnnouncementApproved',
    ];
  }

  /**
   * Notifies moderators once a new announcement is ready to be sent.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onAnnouncementSentForApproval(WorkflowTransitionEvent $event): void {
    if ($event->getField()->getOriginalId() === 'draft') {
      /** @var \Drupal\joinup_group\Entity\AnnouncementInterface $announcement */
      $announcement = $event->getEntity();
      $parameters = ['announcement' => $announcement];
      $user_storage = $this->entityTypeManager->getStorage('user');
      $uids = array_keys($user_storage->getQuery()->accessCheck(FALSE)
        ->condition('roles', 'moderator')
        ->condition('uid', [1, $announcement->getOwnerId()], 'NOT IN')
        ->execute());
      /** @var \Drupal\user\UserInterface[] $moderators */
      $moderators = $user_storage->loadMultiple($uids);
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      foreach ($moderators as $moderator) {
        $this->mailManager->mail('joinup_group', 'group_announcement', $moderator->getEmail(), $langcode, $parameters);
      }
    }
  }

  /**
   * Sends the announcement to group members once it has been approved.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onAnnouncementApproved(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\joinup_group\Entity\AnnouncementInterface $announcement */
    $announcement = $event->getEntity();

    if (!$recipients_count = $announcement->getRecipientsCount()) {
      // Normally, this cannot happen because we're handling this in the form.
      // @see \Drupal\joinup_group\Form\AnnouncementForm::validateForm()
      return;
    }

    // For smaller groups we send the announcements instantly.
    if ($recipients_count <= 50) {
      $recipients = $announcement->getRecipients(0, 50);
      $this->messageDelivery->sendMessageToEmailAddresses($announcement, $recipients);
      return;
    }

    // Announcements for groups with higher number of members are queued.
    $queue = $this->queueFactory->get('send_group_announcements');
    $queue->createQueue();
    // Only pass the announcement to the queue. Let the worker do the heavy job.
    // @see \Drupal\joinup_group\Plugin\QueueWorker\SendGroupAnnouncementsQueueWorker::processItem()
    $queue->createItem(['announcement' => (int) $announcement->id()]);
  }

}
