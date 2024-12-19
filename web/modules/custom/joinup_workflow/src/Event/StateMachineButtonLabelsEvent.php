<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\Event;

use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired when labels for state machine buttons are being applied.
 *
 * Subscribe to this if you want to change the label of a workflow state
 * transition button under certain circumstances. These buttons are shown in the
 * edit forms of content which is subject to workflow.
 *
 * @see \Drupal\joinup_workflow\Plugin\Field\FieldWidget\StateMachineButtons
 */
class StateMachineButtonLabelsEvent extends Event implements StateMachineButtonLabelsEventInterface {

  public function __construct(
    protected readonly EntityWorkflowStateInterface $entity,
    protected array $transitions,
    protected readonly string $stateId,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityWorkflowStateInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions(): array {
    return $this->transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateId(): string {
    return $this->stateId;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransition(string $id): ?WorkflowTransition {
    return $this->transitions[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLabel(string $id, string $label): StateMachineButtonLabelsEventInterface {
    if (!array_key_exists($id, $this->transitions)) {
      throw new \InvalidArgumentException("The transition with ID '$id' does not exist or is not allowed for the current user and workflow state.");
    }

    // The workflow object doesn't have a setter for the label, so we have to
    // replace it with a newly instantiated object and copy existing data.
    $original = $this->transitions[$id];
    $this->transitions[$id] = new WorkflowTransition(
      $original->getId(),
      $label,
      $original->getFromStates(),
      $original->getToState()
    );

    return $this;
  }

}
