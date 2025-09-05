<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface for the state machine helper service.
 */
interface StateMachinePermissionsHelperInterface {

  /**
   * Returns a list of state field definitions.
   *
   * @return array
   *   An associative array where level 0 is the entity type ID, level 1 are
   *   the bundles with a state field and each bundle is an array of state
   *   fields that are attached to the entity.
   */
  public function getStateFieldMap(): array;

  /**
   * Returns a list of possible workflows for a given bundle.
   *
   * By default, we cannot always assume the one workflow the bundle can get
   * because in some cases, the state field can use a callback to retrieve the
   * workflow to use.
   * In these case, the method will return all workflows related to the given
   * entity type ID.
   *
   * @param string $entityTypeId
   *   The entity type ID that the bundle belongs to.
   * @param string $bundle
   *   The entity bundle.
   * @param array $fieldNames
   *   (optional) A list of state fields to derive the workflows from. If left
   *   empty, all fields will be examined.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\Workflow[]
   *   An array of workflows that can match the given bundle. If one workflow
   *   is found, it is still returned as an array.
   */
  public function getPossibleWorkflowsForBundle(string $entityTypeId, string $bundle, array $fieldNames = []): array;

  /**
   * Returns a list of possible workflows for the entity type ID.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return array
   *   The array of possible workflow definition arrays.
   */
  public function getPossibleWorkflowsForEntityType(string $entityTypeId): array;

  /**
   * Returns a list of state field definitions.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string $bundle
   *   The entity bundle.
   * @param array $fieldNames
   *   (optional) An array of field names to return. Returns all state field
   *   definitions of the bundle if left empty.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions.
   */
  public function getEntityStateFieldDefinitions(string $entityTypeId, string $bundle, array $fieldNames = []): array;

  /**
   * Checks if the user has the own or the any permission for the given entity.
   *
   * The method checks both the global and the group permissions if available.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string $operation
   *   The operation to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   An access result object.
   */
  public function checkOwnAnyPermission(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface;

}
