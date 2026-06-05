<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Transition;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Queues group sub-content to be (un)archived when a group is (un)archived.
 */
class GroupArchiveEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly QueueFactory $queueFactory,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'solution.archive.post_transition' => 'onGroupPostArchiveTransition',
      'solution.unarchive.post_transition' => 'onGroupPostUnarchiveTransition',
      'collection.archive.post_transition' => 'onGroupPostArchiveTransition',
    ];
  }

  /**
   * Queues sub-content of an archived group to be archived.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event dispatched after a group was archived.
   */
  public function onGroupPostArchiveTransition(WorkflowTransitionEvent $event): void {
    if (!($group = $event->getEntity()) instanceof GroupInterface) {
      return;
    }

    $this->queueGroupSubContentTransition($group, Transition::Archive);
    $this->messenger->addStatus(
      $this->t(
        'The archiving process of the @type has been initiated. You will receive an email notification as soon as the process is completed.',
        ['@type' => $group->type->entity->label()],
      )
    );
  }

  /**
   * Queues sub-content of an unarchived group to be unarchived.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event dispatched after a group was unarchived.
   */
  public function onGroupPostUnarchiveTransition(WorkflowTransitionEvent $event): void {
    if (!($group = $event->getEntity()) instanceof GroupInterface) {
      return;
    }

    $this->queueGroupSubContentTransition($group, Transition::Unarchive);
    $this->messenger->addStatus(
      $this->t(
        'The unarchiving process of the @type has been initiated.',
        ['@type' => $group->type->entity->label()],
      )
    );
  }

  /**
   * Pushes a (un)archive transition item onto the group archive queue.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group being (un)archived.
   * @param \Drupal\joinup_group\Transition $transition
   *   The transition to apply on the group's sub-content.
   */
  protected function queueGroupSubContentTransition(GroupInterface $group, Transition $transition): void {
    $queue = $this->queueFactory->get('group_archive_related_content');
    $queue->createQueue();
    $data = [
      'user' => $this->currentUser->id(),
      'group' => $group->id(),
      'transition' => $transition,
    ];
    if (!empty($group->motivation)) {
      $data['motivation'] = $group->motivation;
    }
    $queue->createItem($data);
  }

}
