<?php

declare(strict_types=1);

namespace Drupal\joinup_notification\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\MessageArgumentGenerator;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\og\OgRoleInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Notification handler for the notifications related to releases.
 *
 * The notification base conditions are an extension of the solution templates.
 * @codingStandardsIgnoreStart
 * Template 18: release_update
 *   Operation: update
 *   Source state: published
 *   Recipients: solution owners, solution facilitators, moderators
 * Template 19: release_delete
 *   Operation: delete
 *   Source state: published, needs_update
 *   Recipients: owner
 * Template 20: release_approve_proposed
 *   Operation: update
 *   Transition: validate
 *   Source state: needs_update
 *   Recipients: owner
 * Template 23: release_request_changes
 *   Operation: update
 *   Transition: request_deletion
 *   Recipients: owner
 * @codingStandardsIgnoreEnd
 */
class ReleaseRdfSubscriber extends NotificationSubscriberBase {

  const string TEMPLATE_UPDATE_PUBLISHED = 'release_update';
  const string TEMPLATE_DELETE = 'release_delete';
  const string TEMPLATE_APPROVE_PROPOSED = 'release_approve_proposed';
  const string TEMPLATE_REQUEST_CHANGES = 'release_request_changes';

  /**
   * The transition object.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowTransition|null
   */
  protected ?WorkflowTransition $transition;

  /**
   * The workflow object.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface
   */
  protected WorkflowInterface $workflow;

  /**
   * The motivation text passed in the entity.
   *
   * @var string
   */
  protected string $motivation;

  /**
   * The notification event.
   *
   * @var \Drupal\joinup_notification\Event\NotificationEvent
   */
  protected NotificationEvent $event;

  /**
   * The source state of the solution.
   *
   * @var string
   */
  protected string $fromState;

  /**
   * The new state of the solution.
   *
   * @var string
   */
  protected string $toState;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[NotificationEvents::RDF_ENTITY_CRUD] = [
      ['onUpdate'],
      ['onDelete'],
    ];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize(NotificationEvent $event): void {
    parent::initialize($event);

    // Only initialize the workflow if it is available. It is unavailable when
    // the entity is being deleted during cleanup of orphaned group content.
    if (!$this->entity instanceof ReleaseInterface || !$this->entity->hasWorkflow()) {
      return;
    }

    $this->event = $event;
    $this->workflow = $this->entity->getWorkflow();
    if (isset($this->entity->field_state_initial_value)) {
      $this->fromState = $this->entity->field_state_initial_value;
    }
    else {
      $this->fromState = isset($this->entity->original) ? $this->entity->original->getWorkflowState() : 'draft';
    }
    $this->toState = $this->entity->getWorkflowState();
    $this->transition = $this->workflow->findTransition($this->fromState, $this->toState);
    $this->motivation = empty($this->entity->motivation) ? '' : $this->entity->motivation;
  }

  /**
   * Handles notifications on release update.
   *
   * Notifications handled: 18, 20, 23.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onUpdate(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnUpdate()) {
      return;
    }

    $transition_id = $this->transition instanceof WorkflowTransition ? $this->transition->getId() : NULL;
    switch ($transition_id) {
      case 'update_published':
        $user_data = [
          'roles' => [
            'moderator' => [
              self::TEMPLATE_UPDATE_PUBLISHED,
            ],
          ],
          'og_roles' => [
            'rdf_entity-solution-administrator' => [
              self::TEMPLATE_UPDATE_PUBLISHED,
            ],
            'rdf_entity-solution-facilitator' => [
              self::TEMPLATE_UPDATE_PUBLISHED,
            ],
          ],
        ];
        $this->getUsersAndSend($user_data);
        break;

      case 'validate_changes':
        if ($this->fromState === 'needs_update') {
          $user_data = [
            'og_roles' => [
              'rdf_entity-solution-administrator' => [
                self::TEMPLATE_APPROVE_PROPOSED,
              ],
            ],
          ];
          $this->getUsersAndSend($user_data);
        }
        break;

      // Notification ids handled: 10.
      case 'request_changes':
        $user_data = [
          'og_roles' => [
            'rdf_entity-solution-administrator' => [
              self::TEMPLATE_REQUEST_CHANGES,
            ],
            'rdf_entity-solution-facilitator' => [
              self::TEMPLATE_REQUEST_CHANGES,
            ],
          ],
        ];
        $this->getUsersAndSend($user_data);
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
    if (!$this->appliesOnReleases()) {
      return FALSE;
    }

    if ($this->operation !== 'update') {
      return FALSE;
    }

    // Notifications can be sent for asset releases that are updated without
    // changing the workflow state.
    if ($this->fromState === $this->toState) {
      return TRUE;
    }

    if (empty($this->transition)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sends notification when a release is deleted.
   *
   * Notifications handled: 19.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onDelete(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnDelete()) {
      return;
    }

    $user_data = [
      'roles' => [
        'moderator' => [
          self::TEMPLATE_DELETE,
        ],
      ],
      'og_roles' => [
        'rdf_entity-solution-administrator' => [
          self::TEMPLATE_DELETE,
        ],
        'rdf_entity-solution-facilitator' => [
          self::TEMPLATE_DELETE,
        ],
      ],
    ];
    $this->getUsersAndSend($user_data);
  }

  /**
   * Checks if the conditions apply for the onDelete method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnDelete(): bool {
    if (!$this->appliesOnReleases()) {
      return FALSE;
    }

    if ($this->operation !== 'delete') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if the event applies for releases.
   *
   * @return bool
   *   Whether the event applies.
   */
  protected function appliesOnReleases(): bool {
    if ($this->entity->getEntityTypeId() !== 'node') {
      return FALSE;
    }

    if ($this->entity->bundle() !== 'release') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateArguments(EntityInterface $entity): array {
    // PHP does not support covariance on arguments so we cannot narrow the type
    // hint to only release entities. Let's assert the type instead.
    assert($entity instanceof ReleaseInterface, __METHOD__ . ' only supports release entities.');

    $arguments = parent::generateArguments($entity);
    /** @var \Drupal\user\UserInterface $actor */
    $actor = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $motivation = $this->entity->motivation ?? '';
    $arguments['@transition:motivation'] = $motivation;
    $arguments['@entity:release_number'] = $entity->getVersion();

    // Add arguments related to the parent collection or solution.
    $parent = JoinupGroupHelper::getGroup($entity);
    if (!empty($parent)) {
      $arguments += MessageArgumentGenerator::getGroupArguments($parent);
      if (empty($arguments['@actor:role'])) {
        $membership = $this->membershipManager->getMembership($parent, $actor->id());
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
    }

    return $arguments;
  }

  /**
   * Calculates the user data to send the messages with.
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
