<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\owner\Entity\OwnerInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\state_machine\WorkflowGroupManagerInterface;
use Drupal\state_machine\WorkflowManagerInterface;

/**
 * Provides helper methods for discovering fields, workflows and permissions.
 */
class StateMachinePermissionsHelper implements StateMachinePermissionsHelperInterface {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The workflow group manager service.
   *
   * @var \Drupal\state_machine\WorkflowGroupManagerInterface
   */
  protected $workflowGroupManager;

  /**
   * The workflow manager service.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * Constructs a StateMachinePermissionsHelper object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\state_machine\WorkflowGroupManagerInterface $workflow_group_manager
   *   The workflow group manager service.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, WorkflowGroupManagerInterface $workflow_group_manager, WorkflowManagerInterface $workflow_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->workflowGroupManager = $workflow_group_manager;
    $this->workflowManager = $workflow_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateFieldMap(): array {
    $field_definitions = $this->entityFieldManager->getFieldMapByFieldType('state');
    $return = [];
    foreach ($field_definitions as $entity_type_id => $field_states) {
      foreach ($field_states as $field_name => $field_state_data) {
        foreach ($field_state_data['bundles'] as $bundle) {
          $return[$entity_type_id][$bundle][$field_name] = $field_name;
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleWorkflowsForBundle(string $entity_type_id, string $bundle, array $field_names = []): array {
    $workflows = [];
    $field_definitions = $this->getEntityStateFieldDefinitions($entity_type_id, $bundle, $field_names);
    if (empty($field_definitions)) {
      return $workflows;
    }

    foreach ($field_definitions as $field_definition) {
      if (!empty($field_definition->getSetting('workflow_callback'))) {
        // If the 'workflow_callback' setting is set, the workflow can be any
        // workflow of the given entity type id as it depends on parameters from
        // the runtime.
        // Loop over all workflows of the entity type id and add them to the
        // list of the possible workflows.
        foreach ($this->getPossibleWorkflowsForEntityType($entity_type_id) as $workflow_id => $workflow) {
          /** @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow */
          $workflow = $this->workflowManager->createInstance($workflow_id);
          $workflows[$workflow_id] = $workflow;
        }
      }
      elseif ($workflow = $field_definition->getSetting('workflow')) {
        /** @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow */
        $workflow = $this->workflowManager->createInstance($workflow);
        $workflows[$workflow->getId()] = $workflow;
      }
    }
    return $workflows;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleWorkflowsForEntityType(string $entity_type_id): array {
    $groups = array_keys($this->workflowGroupManager->getDefinitionsByEntityType($entity_type_id));
    $workflows = $this->workflowManager->getDefinitions();
    $workflows = array_filter($workflows, function (array $workflow) use ($groups): bool {
      return in_array($workflow['group'], $groups);
    });
    return $workflows;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityStateFieldDefinitions(string $entity_type_id, string $bundle_id, array $field_names = []): array {
    return array_filter($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id), function (FieldDefinitionInterface $field_definition) use ($field_names) {
      return empty($field_names) ?
        $field_definition->getType() === 'state' :
        $field_definition->getType() === 'state' && in_array($field_definition->getName(), $field_names);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function hasOwnAnyPermission(EntityInterface $entity, string $operation, AccountInterface $account): bool {
    // @todo Check if the entity implements EntityOwnerInterface.
    if ($account->hasPermission($this->getGlobalPermissionString($entity, $operation, 'any'))) {
      return TRUE;
    }

    $isOwner = $entity->getOwnerId() === $account->id();
    if ($entity->getOwnerId() === $account->id() && $account->hasPermission($this->getGlobalPermissionString($entity, $operation, 'own'))) {
      return TRUE;
    }

    // @todo this should be injected.
    $ogAccess = \Drupal::getContainer()->get('og.access');
    if ($group = $this->getEntityRelatedGroup($entity)) {
      // The TRUE in the end is so that the custom hooks are not fired - for
      // performance reasons.
      if ($ogAccess->userAccess($group, $this->getGroupPermission($entity, $operation, 'any'), $account, TRUE)->isAllowed()) {
        return TRUE;
      }
      return $isOwner && $ogAccess->userAccess($group, $this->getGroupPermission($entity, $operation, 'own'), $account, TRUE)->isAllowed();
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
    if ($entity instanceof NodeInterface) {
      return "$operation $type {$entity->bundle()} content";
    }
    elseif ($entity instanceof RdfInterface) {
      $operation = $operation === 'update' ? 'edit' : $operation;
      return "$operation $type rdf entity";
    }

    // @todo Add support for other entity types.
    throw new \RuntimeException('This should be handled if happens or the text should be updated.');
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
    if ($entity instanceof NodeInterface) {
      return "$operation $type {$entity->bundle()} content";
    }
    elseif ($entity instanceof RdfInterface) {
      $operation = $operation === 'update' ? 'edit' : $operation;
      return "$operation $type {$entity->bundle()} {$entity->getEntityTypeId()}";
    }

    // @todo Add support for other entity types.
    throw new \RuntimeException('This should be handled if happens or the text should be updated.');
  }

  /**
   * Returns the related group of the entity.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The related group or NULL if the entity does not belong to a group.
   */
  function getEntityRelatedGroup(EntityInterface $entity): ?GroupInterface {
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
    // @todo This should be removed as default behavior.
    elseif ($entity instanceof OwnerInterface) {
      return NULL;
    }

    // @todo Add support for other entity types.
    throw new \RuntimeException("Bundle: {$entity->bundle()} This should be handled if happens or the text should be updated.");

    return NULL;
  }

}
