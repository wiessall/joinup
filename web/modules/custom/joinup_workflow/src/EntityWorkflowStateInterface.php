<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowState;

/**
 * Interface for bundle classes that are subject to workflows.
 */
interface EntityWorkflowStateInterface extends FieldableEntityInterface {

  /**
   * Returns the current workflow state.
   *
   * @return string
   *   The workflow state.
   */
  public function getWorkflowState(): string;

  /**
   * Returns the current state instance.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowState
   *   The current state instance.
   */
  public function getWorkflowStateInstance(): WorkflowState;

  /**
   * Sets the workflow state.
   *
   * @param string $state
   *   The machine readable workflow state value to set.
   *
   * @return \Drupal\joinup_workflow\EntityWorkflowStateInterface
   *   The object for chaining.
   *
   * @todo Once we are on PHP 7.4, leverage return type covariance by replacing
   *   the return type with `self`. This will ensure the entity retains its type
   *   after chaining.
   * @see https://3v4l.org/e3aT1
   */
  public function setWorkflowState(string $state): EntityWorkflowStateInterface;

  /**
   * Returns whether or not a workflow object is associated with the entity.
   *
   * Normally calling code can rely on a workflow object being available, except
   * if the entity is orphaned. Call this method if your code is running as part
   * of the orphan cleanup (e.g. in a entity delete hook).
   *
   * @return bool
   *   TRUE if a workflow state is set, FALSE if not.
   */
  public function hasWorkflow(): bool;

  /**
   * Returns the workflow object.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowInterface
   *   The workflow object.
   *
   * @throws \UnexpectedValueException
   *   Thrown if the workflow object cannot be instantiated because an invalid
   *   workflow ID has been set on the field. During normal usage this will only
   *   occur during the cleanup of orphaned group content which is triggered
   *   after a group is deleted. If your code is running as part of the orphan
   *   cleanup (e.g. in a entity delete hook) then it is recommended to call
   *   ::hasWorkflow() first.
   */
  public function getWorkflow(): WorkflowInterface;

  /**
   * Returns the workflow state field item for this entity.
   *
   * @return \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface
   *   The workflow state field item.
   */
  public function getWorkflowStateField(): StateItemInterface;

  /**
   * Returns the machine name of the workflow state field.
   *
   * @return string
   *   The machine name.
   */
  public function getWorkflowStateFieldName(): string;

  /**
   * Checks whether the entity can be updated depending on the workflow states.
   *
   * Entities which are subject to workflow can only be updated if their current
   * and target workflow states are matching a workflow which is allowed for the
   * current user.
   *
   * This can also be used to check if the entity can be updated while keeping
   * the workflow state unchanged.
   *
   * IMPORTANT: Use this when you want to check the state regardless of the
   * entity's current state. Otherwise, wrong results might be returned in case
   * for example, the entity is being updated and the target state is not the
   * one needed to check.
   *
   * @param string $to_state
   *   The destination state.
   * @param string|null $from_state
   *   The initial workflow state. Defaults to the current workflow state.
   *
   * @return bool
   *   TRUE if the target state is allowed, FALSE if it is not.
   */
  public function isTargetWorkflowStateAllowed(string $to_state, ?string $from_state = NULL): bool;

  /**
   * Gets the allowed transitions for the current state.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   *   The allowed transitions, keyed by transition ID.
   */
  public function getAllowedTransitions(): array;

  /**
   * Check whether at least one transition is available for the current state.
   *
   * @return bool
   *   Whether at least one transition is available for the current state.
   */
  public function hasAllowedTransitions(): bool;

  /**
   * Checks if the transition is allowed for the current user.
   *
   * IMPORTANT: Do NOT use this during a workflow transition. The method will
   * use the state that the entity is moved to or from, depending on the
   * specific point of the workflow that this is called. This can be used when
   * e.g. loading an entity from the database and checking the transition
   * statically.
   *
   * @param string $transition_id
   *   The transition ID.
   *
   * @return bool
   *   TRUE if the transition is allowed, FALSE otherwise.
   */
  public function isTransitionAllowed(string $transition_id): bool;

}
