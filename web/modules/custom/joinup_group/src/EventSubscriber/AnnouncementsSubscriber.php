<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to group announcements transitions.
 */
class AnnouncementsSubscriber implements EventSubscriberInterface {

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
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'announcement.send.post_transition' => 'onAnnouncementSentForApproval',
    ];
  }

  /**
   * Reacts just after an announcement has been approved.
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

}
