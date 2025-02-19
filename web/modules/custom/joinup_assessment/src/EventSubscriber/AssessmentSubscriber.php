<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_assessment\Entity\AssessmentReportInterface;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\EventSubscriber\NotificationSubscriberBase;
use Drupal\joinup_notification\MessageArgumentGenerator;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\og\OgRoleInterface;

/**
 * Notification handler for the notifications related to assessment reports.
 */
class AssessmentSubscriber extends NotificationSubscriberBase {

  const string TEMPLATE_CREATE = 'cc_transition_publish';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      NotificationEvents::NODE_CREATE => ['onCreate'],
    ];
  }

  /**
   * Handles notifications on assessment report create.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onCreate(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnCreate()) {
      return;
    }

    $user_data = [
      'roles' => [
        'moderator' => [
          self::TEMPLATE_CREATE,
        ],
      ],
      'og_roles' => [
        'node-collection-member' => [
          self::TEMPLATE_CREATE,
        ],
      ],
    ];
    $this->getUsersAndSend($user_data);
  }

  /**
   * Checks if the conditions apply for the onUpdate method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnCreate(): bool {
    if (!$this->appliesOnAssessments()) {
      return FALSE;
    }

    if ($this->operation !== 'create') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if the event applies for assessment report.
   *
   * @return bool
   *   Whether the event applies.
   */
  protected function appliesOnAssessments(): bool {
    return $this->entity instanceof AssessmentReportInterface;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateArguments(EntityInterface $entity): array {
    assert($entity instanceof AssessmentReportInterface);

    $arguments = parent::generateArguments($entity);
    /** @var \Drupal\user\UserInterface $actor */
    $actor = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    $arguments['@actor:full_name'] = $actor->getDisplayName();

    $collection = $entity->getGroup();
    $arguments += MessageArgumentGenerator::getGroupArguments($collection);
    // If the role is not yet set, get it from the parent collection|solution.
    if (empty($arguments['@actor:role'])) {
      $membership = $this->membershipManager->getMembership($collection, $actor->id());
      if (!empty($membership)) {
        $roleNames = array_map(function (OgRoleInterface $og_role) {
          return $og_role->getName();
        }, $membership->getRoles());

        if (in_array('administrator', $roleNames)) {
          $arguments['@actor:role'] = $this->t('Owner');
        }
        elseif (in_array('facilitator', $roleNames)) {
          $arguments['@actor:role'] = $this->t('Facilitator');
        }
      }
    }

    return $arguments;
  }

  /**
   * Calculates the user data and sends the messages to.
   *
   * @param array $user_data
   *   The user data array.
   *
   * @see ::getUsersMessages()
   */
  protected function getUsersAndSend(array $user_data): void {
    $user_data = $this->getUsersMessages($user_data);
    $this->sendUserDataMessages($user_data);
  }

}
