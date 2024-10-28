<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\Plugin\views\filter;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Plugin\views\filter\State;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_permissions\StateMachinePermissionsHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interoperable Europe Portal content state.
 *
 * Workflow for nodes are calculated based on the associated group. We can not
 * use the standard approach because state filter does not use a real entity,
 * it does not have associated group, so workflows can not be defined.
 *
 * @see joinup_community_content_workflow_selector()
 */
class JoinupContentState extends State {

  /**
   * Constructs a new joinup content state object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The workflow manager service.
   * @param \Drupal\state_machine_permissions\StateMachinePermissionsHelperInterface $stateMachinePermissionsHelper
   *   State machine permissions helper.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    protected WorkflowManagerInterface $workflowManager,
    protected StateMachinePermissionsHelperInterface $stateMachinePermissionsHelper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.workflow'),
      $container->get('state_machine_permissions.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getWorkflows(EntityTypeInterface $entity_type, $field_name): array {
    if ($entity_type->id() !== 'node') {
      return parent::getWorkflows($entity_type, $field_name);
    }

    $workflows = [];
    foreach ($this->stateMachinePermissionsHelper->getPossibleWorkflowsForEntityType($entity_type->id()) as $definition) {
      $workflows[$definition['id']] = $this->workflowManager->createInstance($definition['id']);
    }

    return $workflows;
  }

}
