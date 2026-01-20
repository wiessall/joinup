<?php

declare(strict_types=1);

namespace Drupal\joinup_notification\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\og\OgRoleInterface;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\user\UserInterface;

/**
 * Handles notifications related to collections.
 */
class CollectionSubscriber extends NotificationSubscriberBase {

  const string TEMPLATE_APPROVE_EDIT = 'col_approve_edit';
  const string TEMPLATE_APPROVE_NEW = 'col_approve_new';
  const string TEMPLATE_ARCHIVE_DELETE_APPROVE_OWNER = 'col_arc_del_apr_own';
  const string TEMPLATE_ARCHIVE_DELETE_MEMBERS = 'col_arc_del_members';
  const string TEMPLATE_ARCHIVE_DELETE_NO_REQUEST = 'col_arc_del_no_request';
  const string TEMPLATE_ARCHIVE_DELETE_REJECT = 'col_arc_del_rej';
  const string TEMPLATE_ARCHIVE_DELETE_SOLUTIONS_ORPHANED = 'col_arc_del_sol_no_affiliates';
  const string TEMPLATE_DELETION_BY_MODERATOR = 'col_deletion_by_moderator';
  const string TEMPLATE_PROPOSE_EDIT_MODERATORS = 'col_propose_edit_mod';
  const string TEMPLATE_PROPOSE_EDIT_OWNER = 'col_propose_edit_own';
  const string TEMPLATE_PROPOSE_NEW = 'col_propose_new';
  const string TEMPLATE_REQUEST_ARCHIVAL_DELETION = 'col_req_arch_del';

  /**
   * The transition object.
   */
  protected WorkflowTransition|null $transition;

  /**
   * The workflow object.
   */
  protected Workflow $workflow;

  /**
   * The motivation text passed in the entity.
   */
  protected string $motivation;

  /**
   * Whether the entity has a published version.
   */
  protected bool $hasPublished;

  /**
   * The notification event.
   */
  protected NotificationEvent $event;

  /**
   * The source state of the collection.
   */
  protected string $fromState;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Notification ID 1 - Propose new collection.
      NotificationEvents::NODE_CREATE => ['onCreate'],
      // All notification IDs.
      NotificationEvents::NODE_UPDATE => ['onUpdate'],
      // Notification ID 9, 12, 13, 14.
      NotificationEvents::NODE_DELETE => ['onDelete'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize(NotificationEvent $event): void {
    parent::initialize($event);
    if (!$this->entity instanceof CollectionInterface) {
      return;
    }

    $this->event = $event;
    $this->workflow = $this->entity->getWorkflow();
    $this->fromState = $this->entity->getOriginal()?->getWorkflowState() ?? 'draft';
    $to_state = $this->entity->getWorkflowState();
    $this->transition = $this->workflow->findTransition($this->fromState, $to_state);
    $this->motivation = empty($this->entity->motivation) ? '' : $this->entity->motivation;
    $this->hasPublished = $this->entity->had_published_version ?? FALSE;
  }

  /**
   * Sends notification if the collection is created in proposed state.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onCreate(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnCreate()) {
      return;
    }
    $template_id = self::TEMPLATE_PROPOSE_NEW;
    $user_data_array = ['roles' => ['moderator' => [$template_id]]];
    $user_data = $this->getUsersMessages($user_data_array);
    $this->sendUserDataMessages($user_data);
  }

  /**
   * Checks if the conditions apply for the onCreate method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnCreate(): bool {
    if (!$this->appliesOnCollections()) {
      return FALSE;
    }

    if ($this->operation !== 'create') {
      return FALSE;
    }

    if (empty($this->transition)) {
      return FALSE;
    }

    if ($this->transition->getId() !== 'propose') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Handles notifications on collection update.
   *
   * Notifications handled: All.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onUpdate(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnUpdate()) {
      return;
    }

    switch ($this->transition->getId()) {
      case 'propose':
        $this->notificationPropose();
        break;

      case 'update_published':
        $this->notificationValidate();
        break;

      case 'validate':
        if ($this->fromState === 'proposed') {
          $this->notificationValidate();
        }
        elseif ($this->fromState === 'archival_request') {
          $this->notificationRejectArchivalDeletion();
        }
        break;

      case 'request_archival':
        $this->notificationRequestArchivalDeletion();
        break;

      case 'archive':
        $this->notificationArchiveDelete();
        break;

    }
  }

  /**
   * Checks if the conditions apply for the onUpdate method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnUpdate(): bool {
    if (!$this->appliesOnCollections()) {
      return FALSE;
    }

    if ($this->operation !== 'update') {
      return FALSE;
    }

    if (empty($this->transition)) {
      return FALSE;
    }

    $transitions_with_notification = [
      'propose',
      'validate',
      'request_archival',
      'archive',
      'update_published',
    ];
    if (!in_array($this->transition->getId(), $transitions_with_notification)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sends notification if the collection is deleted.
   *
   * Notifications handled: 9, 12, 13, 14.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onDelete(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnDelete()) {
      return;
    }

    $this->notificationArchiveDelete();
  }

  /**
   * Checks if the conditions apply for the onDelete method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnDelete(): bool {
    if (!$this->appliesOnCollections()) {
      return FALSE;
    }

    if ($this->operation !== 'delete') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sends a notification for proposing a collection.
   *
   * @codingStandardsIgnoreStart
   * Notification id 1:
   *  - Event: A new collection is proposed.
   *  - Recipient: Moderators
   * Notification id 4:
   *  - Event: A collection which has been published is proposed.
   *  - Recipient: Moderators
   * Notification id 5:
   *  - Event: A collection which has been published is proposed.
   *  - Recipient: Owner
   *@codingStandardsIgnoreEnd
   *
   * If the entity does not have a published version, send notification id 1 to
   * the moderators, otherwise, send templates 4, 5 to the owner and the
   * moderator respectively.
   */
  protected function notificationPropose(): void {
    if (!$this->hasPublished) {
      $user_data = ['roles' => ['moderator' => [self::TEMPLATE_PROPOSE_NEW]]];
    }
    else {
      $user_data = [
        'roles' => [
          'moderator' => [
            self::TEMPLATE_PROPOSE_EDIT_MODERATORS,
          ],
        ],
        'og_roles' => [
          'node-collection-administrator' => [
            self::TEMPLATE_PROPOSE_EDIT_OWNER,
          ],
        ],
      ];
    }

    $this->getUsersAndSend($user_data);
  }

  /**
   * Sends a notification for validating a collection.
   *
   * Notification id 2, 6.
   * Notification 1 is sent when a new entity is published. 6 is sent when a
   * modification is approved.
   */
  protected function notificationValidate(): void {
    $template_id = $this->hasPublished ? self::TEMPLATE_APPROVE_EDIT : self::TEMPLATE_APPROVE_NEW;
    $user_data = [
      'og_roles' => [
        'node-collection-administrator' => [
          $template_id,
        ],
      ],
    ];
    $this->getUsersAndSend($user_data);
  }

  /**
   * Sends a notification on archival request.
   *
   * Notification id 8.
   */
  protected function notificationRequestArchivalDeletion(): void {
    $template_id = self::TEMPLATE_REQUEST_ARCHIVAL_DELETION;
    $user_data = ['roles' => ['moderator' => [$template_id]]];
    $this->getUsersAndSend($user_data);
  }

  /**
   * Sends a notification on rejecting an archival request.
   *
   * Notification id 10.
   */
  protected function notificationRejectArchivalDeletion(): void {
    $template_id = self::TEMPLATE_ARCHIVE_DELETE_REJECT;
    $user_data = [
      'og_roles' => [
        'node-collection-administrator' => [
          $template_id,
        ],
      ],
    ];
    $this->getUsersAndSend($user_data);
  }

  /**
   * Sends a notification on archiving a collection.
   *
   * Notification id 9, 13, 14.
   *
   * Notification 9 notifies the owner that their request to archive/delete a
   * collection was approved.
   * Notification 13 notifies the owner that their collection has been archived
   * or deleted without prior request.
   * Only one of both are sent depending on the current state of the collection.
   */
  protected function notificationArchiveDelete(): void {
    // Template id 9. Notify the owner.
    $template_id = $this->isTransitionRequested() ? self::TEMPLATE_ARCHIVE_DELETE_APPROVE_OWNER : self::TEMPLATE_ARCHIVE_DELETE_NO_REQUEST;

    // Send a custom notification to the owner if their collection was deleted
    // by a moderator.
    $actor = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    assert($actor instanceof UserInterface);
    if ($actor->hasRole('moderator') && $this->operation === 'delete') {
      $template_id = self::TEMPLATE_DELETION_BY_MODERATOR;
    }

    $user_data = [
      'og_roles' => [
        'node-collection-administrator' => [
          $template_id,
        ],
      ],
    ];
    $user_data = $this->getUsersMessages($user_data);
    $this->sendUserDataMessages($user_data);

    // Next, notify all owners of affiliated solutions.
    foreach ($this->entity->get('solutions')->referencedEntities() as $solution) {
      if (!empty($solution)) {
        $this->notifySolutionOwners($solution);
      }
    }

    // Avoid sending again to the owner.
    $uids_to_skip = [];
    foreach ($user_data as $user_ids) {
      $uids_to_skip += array_values($user_ids);
    }

    // Last, send an email to all members of the collection.
    $user_data = [
      'og_roles' => [
        'node-collection-member' => [
          self::TEMPLATE_ARCHIVE_DELETE_MEMBERS,
        ],
      ],
    ];
    $user_data = $this->getUsersMessages($user_data);
    foreach ($uids_to_skip as $uid) {
      foreach ($user_data as $message_id => $user_ids) {
        unset($user_data[$message_id][$uid]);
      }
    }

    $this->sendUserDataMessages($user_data);
  }

  /**
   * Sends a notification to solution owners affiliated with the collection.
   *
   * Notification id 11.
   *
   * Notification 11 is sent to the solution owners in which the collection that
   * is archived/deleted is the only affiliate, prompting them to take action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $solution
   *   The solution entity.
   */
  protected function notifySolutionOwners(EntityInterface $solution): void {
    $user_data = [
      'og_roles' => [
        'node-solution-administrator' => [
          self::TEMPLATE_ARCHIVE_DELETE_SOLUTIONS_ORPHANED,
        ],
      ],
    ];
    $user_data = $this->getUsersMessages($user_data, $solution);
    $arguments = [
      '@affiliate:title' => $solution->label(),
      '@affiliate:url' => $solution->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $this->sendUserDataMessages($user_data, $arguments);
  }

  /**
   * Checks if the event applies for the update operation.
   *
   * @return bool
   *   Whether the event applies.
   */
  protected function appliesOnCollections(): bool {
    return $this->entity instanceof CollectionInterface;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateArguments(EntityInterface $entity): array {
    $arguments = parent::generateArguments($entity);
    /** @var \Drupal\user\UserInterface $actor */
    $actor = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $motivation = $this->entity->motivation ?? '';
    $arguments['@transition:motivation'] = $motivation;

    if (empty($arguments['@actor:role'])) {
      $membership = $this->membershipManager->getMembership($entity, $actor->id());
      if (!empty($membership)) {
        $role_names = array_map(function (OgRoleInterface $og_role) {
          return $og_role->getName();
        }, $membership->getRoles());

        if (in_array('administrator', $role_names)) {
          $arguments['@actor:role'] = $this->t('Owner');
        }
        elseif (in_array('facilitator', $role_names)) {
          $arguments['@actor:role'] = $this->t('Facilitator');
        }
      }
      $arguments['@actor:full_name'] = $actor->getDisplayName();
    }

    if ($this->operation === 'delete') {
      $arguments['@transition:request_action'] = 'delete';
      $arguments['@transition:request_action:past'] = 'deleted';
      $arguments['@transition:archive:extra:owner'] = '';
      $arguments['@transition:archive:extra:members'] = '';
    }
    elseif (in_array($this->transition->getId(), ['archive', 'request_archival']) || ($this->transition->getId() === 'validate' && $this->fromState === 'archival_request')) {
      $arguments['@transition:request_action'] = 'archive';
      $arguments['@transition:request_action:past'] = 'archived';
      if ($this->transition->getId() === 'archive') {
        $arguments['@transition:archive:extra:owner'] = $this->t('You can verify the outcome of your request by clicking on @entity:url', [
          '@entity:url' => $arguments['@entity:url'],
        ]);
        $arguments['@transition:archive:extra:members'] = $this->t('The reason for being @transition:request_action:past is: @transition:motivation', [
          '@transition:request_action:past' => $arguments['@transition:request_action:past'],
          '@transition:motivation' => $arguments['@transition:motivation'],
        ]);
      }
    }

    return $arguments;
  }

  /**
   * Calculates the user data to send the messages with.
   *
   * @param array $user_data
   *   The user data array in the format as used by ::getUsersMessages().
   *
   * @see ::getUsersMessages()
   */
  protected function getUsersAndSend(array $user_data): void {
    $user_data = $this->getUsersMessages($user_data);
    $this->sendUserDataMessages($user_data);
  }

  /**
   * Checks whether the action is requested.
   *
   * Applies only for archival request.
   *
   * @return bool
   *   Whether the action is requested. Returns TRUE if the transition is caused
   *   by a moderator approving the requested archival of a collection.
   */
  protected function isTransitionRequested(): bool {
    assert($this->entity instanceof EntityWorkflowStateInterface);
    $state = $this->entity->getWorkflowState();
    return $state === 'archived' && $this->transition && $this->transition->getId() === 'archive';
  }

}
