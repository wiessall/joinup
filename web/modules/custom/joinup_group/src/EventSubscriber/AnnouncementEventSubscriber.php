<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_federation\RdfEntityReferenceFieldItemList;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\EventSubscriber\NotificationSubscriberBase;
use Drupal\joinup_notification\NotificationEvents;

/**
 * Handles notifications related to announcements.
 */
class AnnouncementEventSubscriber extends NotificationSubscriberBase {

  const RECIPIENTS = [
    'create' => [
      'roles' => ['moderator' => ['announcement_create']],
    ],
  ];

  /**
   * The entity that the comment belongs to.
   */
  protected RdfEntityReferenceFieldItemList $parent;

  /**
   * The group that the entity of the comment belongs to.
   *
   * If the comment belongs to a group, then the group is the same as the parent
   * variable.
   */
  protected GroupInterface $group;

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

  /**
   * {@inheritdoc}
   */
  protected function generateArguments(EntityInterface $entity): array {
    $arguments = parent::generateArguments($entity);
    if ($entity->hasField('subject')) {
      $arguments['@entity:subject'] = $entity->get('subject')->value;
    }

    return $arguments;
  }

}
