<?php

declare(strict_types=1);

namespace Drupal\asset_distribution\Plugin\WorkflowStatePermission;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\asset_distribution\Entity\AssetDistributionInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Drupal\workflow_state_permission\WorkflowStatePermissionPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks whether changing workflow states is permitted for a given user.
 *
 * @WorkflowStatePermission(
 *   id = "asset_distribution",
 * )
 */
class DistributionWorkflowStatePermission extends PluginBase implements WorkflowStatePermissionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a DistributionWorkflowStatePermission object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\joinup_workflow\WorkflowHelperInterface $workflowHelper
   *   The workflow helper service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, protected ConfigFactoryInterface $configFactory, protected WorkflowHelperInterface $workflowHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('joinup_workflow.workflow_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof AssetDistributionInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function isStateUpdatePermitted(AccountInterface $account, EntityInterface $entity, WorkflowInterface $workflow, string $from_state, string $to_state): bool {
    if ($account->hasPermission('administer rdf entity')) {
      return TRUE;
    }

    // Distributions do not have an owner so own permissions are not applicable.
    $anyPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $workflow->findTransition($from_state, $to_state), 'any');
    return $account->hasPermission($anyPermission) || $this->workflowHelper->hasOgPermission($anyPermission, $entity->getSolution(), $account);
  }

}
