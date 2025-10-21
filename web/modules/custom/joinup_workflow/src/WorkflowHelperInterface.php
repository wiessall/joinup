<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Stringable;

/**
 * Interface for services that provide workflow related helper methods.
 */
interface WorkflowHelperInterface {

  /**
   * Returns the available target states of an entity for the given user.
   *
   * If no user is passed, the logged in user is checked. If no user is logged
   * in, an anonymous account is passed.
   *
   * This will return all target states that are available to the user, meaning
   * the transition states and the current state if it is allowed to update the
   * entity without changing the state.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to check.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account for which to check the available workflow states. If
   *   omitted the currently logged in user will be checked.
   *
   * @return string[]
   *   An array of available target workflow states.
   */
  public function getAvailableTargetStates(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): array;

  /**
   * Returns the available transitions labels of an entity for the given user.
   *
   * If no user is passed, the logged in user is checked. If no user is logged
   * in, an anonymous account is passed.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity with the states.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account interface object. Can be left empty.
   *
   * @return array
   *   An array of transition labels.
   */
  public function getAvailableTransitionsLabels(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): array;

  /**
   * Returns the available transition states of an entity for the given user.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity with the states.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account interface object. Can be left empty.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   *   An array of transition objects.
   */
  public function getAvailableTransitions(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): array;

  /**
   * Checks if the user has access to any transitions for the given entity.
   *
   * This method provides proper cache metadata and should be used for access
   * checking instead of checking if getAvailableTransitions() is empty.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to check transition access for.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account for which to check access. If omitted the currently
   *   logged in user will be checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   An access result object.
   */
  public function checkTransitionAccess(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): AccessResultInterface;

  /**
   * Returns the state field definitions of an entity.
   *
   * @param string $entityTypeId
   *   The entity type ID for which to return the state field definitions.
   * @param string $bundle
   *   The bundle ID for which to return the state field definitions.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Returns an array of state field definitions.
   */
  public function getEntityStateFieldDefinitions(string $entityTypeId, string $bundle): array;

  /**
   * Returns the state field definition of an entity.
   *
   * In the current project every entity with a state has only one state field
   * so this method returns the first available field definitions of the
   * entity's field definitions otherwise it returns NULL.
   *
   * @param string $entityTypeId
   *   The entity type ID for which to return the state field definition.
   * @param string $bundle
   *   The bundle ID for which to return the state field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   Returns the state field definition of the entity or NULL if none is
   *   found.
   */
  public function getEntityStateFieldDefinition(string $entityTypeId, string $bundle): ?FieldDefinitionInterface;

  /**
   * Checks if a state is set as published in a certain workflow.
   *
   * @param string $stateId
   *   The ID of the state to check.
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow the state belongs to.
   *
   * @return bool
   *   TRUE if the state is set as published in the workflow, FALSE otherwise.
   *
   * @throwns \InvalidArgumentException
   *   Thrown when the workflow is not plugin based, because this is required to
   *   retrieve the publication state from the workflow states.
   */
  public function isWorkflowStatePublished(string $stateId, WorkflowInterface $workflow): bool;

  /**
   * Archives an entity by creating a new default revision if possible.
   *
   * @param \Drupal\joinup_workflow\EntityWorkflowStateInterface $entity
   *   The node to archive.
   * @param string|\Stringable|\Drupal\Core\StringTranslation\TranslatableMarkup|null $revisionLogMessage
   *   An optional message for the new revision. A default "Archived by user N"
   *   message is used if omitted. Use empty string to not set any revision log
   *   message.
   *
   * @return \Drupal\joinup_workflow\EntityWorkflowStateInterface
   *   The archived entity.
   *
   * @throws \Exception
   *     If the entity cannot be archived.
   */
  public function archiveEntity(EntityWorkflowStateInterface $entity, null|string|Stringable|TranslatableMarkup $revisionLogMessage = NULL): EntityWorkflowStateInterface;

  /**
   * Un-archives an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to un-archive.
   * @param string|\Stringable|\Drupal\Core\StringTranslation\TranslatableMarkup|null $revisionLogMessage
   *   An optional message for the new revision. A default "Unrchived by user N"
   *   message is used if omitted. Use empty string to not set any revision log
   *   message.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The unarchived entity.
   *
   * @throws \Exception
   *   If the entity cannot be unarchived.
   */
  public function unarchiveEntity(EntityInterface $entity, string|Stringable|TranslatableMarkup|null $revisionLogMessage = NULL): EntityInterface;

  /**
   * Checks whether the user can not un-archive content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   Whether the user can not un-archive content.
   */
  public function accountCanNotUnarchive(EntityInterface $entity, AccountInterface $account): bool;

}
