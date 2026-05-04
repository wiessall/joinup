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

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MailManagerInterface $mailManager,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly JoinupMessageDeliveryInterface $messageDelivery,
    protected readonly QueueFactory $queueFactory,
  ) {
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
      $accounts = $this->entityTypeManager->getStorage('user')->loadMultiple($recipients);
      $this->messageDelivery->sendMessageToMultipleUsers($announcement, $accounts);
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
