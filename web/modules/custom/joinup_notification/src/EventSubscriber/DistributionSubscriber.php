<?php

declare(strict_types=1);

namespace Drupal\joinup_notification\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\MessageArgumentGenerator;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Exception\MissingSolutionException;
use Drupal\og\OgRoleInterface;

/**
 * Notification handler for the notifications related to distributions.
 *
 * The notification base conditions are an extension of the solution templates.
 * @codingStandardsIgnoreStart
 * Template 21: distribution_update
 *   Operation: update
 *   Source state: published
 *   Recipients: solution owners, solution facilitators, moderators
 * Template 22: distribution_delete
 *   Operation: delete
 *   Source state: published, needs_update
 *   Recipients: owner
 * @codingStandardsIgnoreEnd
 */
class DistributionSubscriber extends NotificationSubscriberBase {

  const string TEMPLATE_UPDATE = 'distribution_update';
  const string TEMPLATE_DELETE = 'distribution_delete';

  /**
   * The notification event.
   *
   * @var \Drupal\joinup_notification\Event\NotificationEvent
   */
  protected NotificationEvent $event;

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
    if ($this->entity->bundle() !== 'distribution') {
      return;
    }

    $this->event = $event;
  }

  /**
   * Handles notifications on distribution update.
   *
   * Notifications handled: 21.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onUpdate(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnUpdate()) {
      return;
    }

    $user_data = [
      'og_roles' => [
        'node-solution-administrator' => [
          self::TEMPLATE_UPDATE,
        ],
        'node-solution-facilitator' => [
          self::TEMPLATE_UPDATE,
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
  protected function appliesOnUpdate(): bool {
    if (!$this->appliesOnDistributions()) {
      return FALSE;
    }

    if ($this->operation !== 'update') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sends notification when a distribution is deleted.
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
      'og_roles' => [
        'node-solution-administrator' => [
          self::TEMPLATE_DELETE,
        ],
        'node-solution-facilitator' => [
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
    if (!$this->appliesOnDistributions()) {
      return FALSE;
    }

    if ($this->operation !== 'delete') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if the event applies for distributions.
   *
   * @return bool
   *   Whether the event applies.
   */
  protected function appliesOnDistributions(): bool {
    if ($this->entity->getEntityTypeId() !== 'node') {
      return FALSE;
    }

    if ($this->entity->bundle() !== 'distribution') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateArguments(EntityInterface $entity): array {
    assert($entity instanceof DistributionInterface);

    $arguments = parent::generateArguments($entity);
    $actor = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    /** @var \Drupal\user\UserInterface $actor */
    $arguments['@release:info:with_version'] = '';

    // Add arguments related to the parent collection or solution.
    $parent = $entity->getParent();
    if (!empty($parent) && $parent instanceof ReleaseInterface) {
      $arguments['@release:info:with_version'] = $this->t('of the release @release, @version', [
        '@release' => $parent->label(),
        '@version' => $parent->getVersion(),
      ]);
    }

    try {
      $solution = $entity->getSolution();
      $arguments += MessageArgumentGenerator::getGroupArguments($solution);
      if (empty($arguments['@actor:role'])) {
        $membership = $this->membershipManager->getMembership($solution, $actor->id());
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
    catch (MissingSolutionException $e) {
      // @todo This shouldn't occur in normal usage, but at the moment there are
      //   some orphaned distributions remaining in the database. Once these are
      //   cleaned up we can transform the MissingSolutionException to a runtime
      //   exception and this try-catch block is no longer required.
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6218
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

  /**
   * Returns the state of the release related to the event.
   *
   * @return string
   *   The current state.
   */
  protected function getReleaseState(): string {
    return $this->entity->get('field_state')->first()->value;
  }

}
