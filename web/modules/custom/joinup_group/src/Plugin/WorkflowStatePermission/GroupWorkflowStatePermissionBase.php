<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\WorkflowStatePermission;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Drupal\user\EntityOwnerInterface;
use Drupal\workflow_state_permission\WorkflowStatePermissionPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for workflow state permission plugins for group related entities.
 */
abstract class GroupWorkflowStatePermissionBase extends PluginBase implements WorkflowStatePermissionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The workflow helper service.
   *
   * @var \Drupal\joinup_workflow\WorkflowHelperInterface
   */
  protected $workflowHelper;

  /**
   * Constructs a workflow state permission plugin for group related entities.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\joinup_workflow\WorkflowHelperInterface $workflowHelper
   *   The workflow helper service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, WorkflowHelperInterface $workflowHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->workflowHelper = $workflowHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('joinup_workflow.workflow_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isStateUpdatePermitted(AccountInterface $account, EntityInterface $entity, WorkflowInterface $workflow, string $from_state, string $to_state): bool {
    if ($account->hasPermission($entity->getEntityType()->getAdminPermission())) {
      return TRUE;
    }

    $anyPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $workflow->findTransition($from_state, $to_state), 'any');
    if ($account->hasPermission($anyPermission) || $this->workflowHelper->hasOgPermission($anyPermission, $entity->getGroup(), $account)) {
      return TRUE;
    }

    if (!$entity instanceof EntityOwnerInterface) {
      return FALSE;
    }

    if ($account->id() === $entity->getOwnerId()) {
      $ownPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $workflow->findTransition($from_state, $to_state), 'own');
      return $account->hasPermission($ownPermission) || $this->workflowHelper->hasOgPermission($ownPermission, $entity->getGroup(), $account);
    }

    return FALSE;
  }

}
