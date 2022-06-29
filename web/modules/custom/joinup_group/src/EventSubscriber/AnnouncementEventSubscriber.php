<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\EventSubscriber\NotificationSubscriberBase;
use Drupal\joinup_notification\NotificationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles notifications related to announcements.
 */
class AnnouncementEventSubscriber extends NotificationSubscriberBase implements EventSubscriberInterface {

  const ANNOUNCEMENT_CREATE = 'announcement_create';
  const RECIPIENTS = [
    'create' => [
      'roles' => ['moderator' => [self::ANNOUNCEMENT_CREATE]],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[NotificationEvents::ANNOUNCEMENT_CREATE] = [
      ['onCreate'],
    ];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize(NotificationEvent $event): void {
    parent::initialize($event);
    /** @var \Drupal\message\MessageInterface $message */
    $message = $this->entity;
    $this->parent = $message->get('group_reference');
    if (!empty($this->parent)) {
      if ($this->parent instanceof GroupInterface) {
        $this->group = $this->parent;
      }
      elseif ($this->parent instanceof GroupContentInterface) {
        $this->group = JoinupGroupHelper::getGroup($this->parent);
      }
    }
  }

  /**
   * Sends notifications on a create operation.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The event object.
   */
  public function onCreate(NotificationEvent $event): void {
    $this->initialize($event);
    $user_data = self::RECIPIENTS['create'];
    $user_data = $this->getUsersMessages($user_data);
    $this->sendUserDataMessages($user_data);
  }

}
