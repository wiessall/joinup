<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\og\OgAccessInterface;
use Drupal\state_machine\WorkflowGroupManagerInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides helper methods for discovering fields, workflows and permissions.
 */
class StateMachinePermissionsHelper implements StateMachinePermissionsHelperInterface {

  /**
   * Constructs a StateMachinePermissionsHelper object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\state_machine\WorkflowGroupManagerInterface $workflowGroupManager
   *   The workflow group manager service.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The workflow manager service.
   * @param \Drupal\og\OgAccessInterface $ogAccess
   *   The og access service.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected WorkflowGroupManagerInterface $workflowGroupManager,
    protected WorkflowManagerInterface $workflowManager,
    protected OgAccessInterface $ogAccess,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStateFieldMap(): array {
    $fieldDefinitions = $this->entityFieldManager->getFieldMapByFieldType('state');
    $return = [];
    foreach ($fieldDefinitions as $entityTypeId => $fieldStates) {
      foreach ($fieldStates as $fieldName => $fieldStateData) {
        foreach ($fieldStateData['bundles'] as $bundle) {
          $return[$entityTypeId][$bundle][$fieldName] = $fieldName;
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleWorkflowsForBundle(string $entityTypeId, string $bundle, array $fieldNames = []): array {
    $workflows = [];
    $fieldDefinitions = $this->getEntityStateFieldDefinitions($entityTypeId, $bundle, $fieldNames);
    if (empty($fieldDefinitions)) {
      return $workflows;
    }

    foreach ($fieldDefinitions as $fieldDefinition) {
      if (!empty($fieldDefinition->getSetting('workflow_callback'))) {
        // If the 'workflow_callback' setting is set, the workflow can be any
        // workflow of the given entity type id as it depends on parameters from
        // the runtime.
        // Loop over all workflows of the entity type id and add them to the
        // list of the possible workflows.
        foreach ($this->getPossibleWorkflowsForEntityType($entityTypeId) as $workflowId => $workflow) {
          /** @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow */
          $workflow = $this->workflowManager->createInstance($workflowId);
          $workflows[$workflowId] = $workflow;
        }
      }
      elseif ($workflow = $fieldDefinition->getSetting('workflow')) {
        $workflow = $this->workflowManager->createInstance($workflow);
        $workflows[$workflow->getId()] = $workflow;
      }
    }
    return $workflows;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleWorkflowsForEntityType(string $entityTypeId): array {
    $groups = array_keys($this->workflowGroupManager->getDefinitionsByEntityType($entityTypeId));
    $workflows = $this->workflowManager->getDefinitions();
    return array_filter($workflows, function (array $workflow) use ($groups): bool {
      return in_array($workflow['group'], $groups);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityStateFieldDefinitions(string $entityTypeId, string $bundle, array $fieldNames = []): array {
    return array_filter($this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle), function (FieldDefinitionInterface $fieldDefinition) use ($fieldNames) {
      return empty($fieldNames) ?
        $fieldDefinition->getType() === 'state' :
        $fieldDefinition->getType() === 'state' && in_array($fieldDefinition->getName(), $fieldNames);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function hasOwnAnyPermission(EntityInterface $entity, string $operation, AccountInterface $account): bool {
    if ($account->hasPermission($this->getGlobalPermissionString($entity, $operation, 'any'))) {
      return TRUE;
    }

    $isOwner = ($entity instanceof EntityOwnerInterface) && $entity->getOwnerId() === $account->id();
    if ($isOwner && $account->hasPermission($this->getGlobalPermissionString($entity, $operation, 'own'))) {
      return TRUE;
    }

    if ($group = $this->getEntityRelatedGroup($entity)) {
      // The TRUE in the end is so that the custom hooks are not fired - for
      // performance reasons.
      if ($this->ogAccess->userAccess($group, $this->getGroupPermission($entity, $operation, 'any'), $account, TRUE)->isAllowed()) {
        return TRUE;
      }
      return $isOwner && $this->ogAccess->userAccess($group, $this->getGroupPermission($entity, $operation, 'own'), $account, TRUE)->isAllowed();
    }

    return FALSE;
  }

  /**
   * Returns the global any permission for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string $operation
   *   The operation to check.
   * @param string $type
   *   The type of permission to check. Can be 'any' or 'own'.
   *
   * @return string
   *   The global any permission.
   */
  protected function getGlobalPermissionString(EntityInterface $entity, string $operation, string $type): string {
    \assert(in_array($entity->getEntityTypeId(), ['node', 'rdf_entity'], TRUE));
    if ($entity instanceof NodeInterface) {
      return "$operation $type {$entity->bundle()} content";
    }

    // Then, it is an RDF entity.
    $operation = $operation === 'update' ? 'edit' : $operation;
    return "$operation $type rdf entity";
  }

  /**
   * Returns the group permission for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string $operation
   *   The operation to check.
   * @param string $type
   *   The type of permission to check. Can be 'any' or 'own'.
   *
   * @return string
   *   The group permission.
   */
  protected function getGroupPermission(EntityInterface $entity, string $operation, string $type): string {
    \assert(in_array($entity->getEntityTypeId(), ['node', 'rdf_entity'], TRUE));
    if ($entity instanceof NodeInterface) {
      return "$operation $type {$entity->bundle()} content";
    }

    // Then, it is an RDF entity.
    $operation = $operation === 'update' ? 'edit' : $operation;
    return "$operation $type {$entity->bundle()} {$entity->getEntityTypeId()}";
  }

  /**
   * Returns the related group of the entity.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The related group or NULL if the entity does not belong to a group.
   */
  public function getEntityRelatedGroup(EntityInterface $entity): ?GroupInterface {
    if ($entity instanceof CollectionInterface) {
      return $entity;
    }
    if ($entity instanceof ContactInformationInterface) {
      return $entity->getRelatedGroup();
    }
    // Solutions are also group content.
    elseif ($entity instanceof GroupContentInterface) {
      return $entity->getGroup();
    }

    return NULL;
  }

}
