<?php

declare(strict_types = 1);

namespace Drupal\joinup_workflow;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide dynamic permissions for the workflow.
 */
class JoinupWorkflowPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a JoinupWorkflowPermissions instance.
   */
  public function __construct(
    protected WorkflowManagerInterface $workflowManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.workflow'),
    );
  }

  /**
   * Build permissions for each workflow.
   *
   * @return array
   *   The workflow permissions.
   */
  public function workflowPermissions(): array {
    $workflowDefinitions = $this->workflowManager->getDefinitions();
    $permissions = [];

    foreach ($workflowDefinitions as $workflowDefinition) {
      $permissions += $this->buildPermissions($this->workflowManager->createInstance($workflowDefinition['id']));
    }

    return $permissions;
  }

  /**
   * Return all the permissions available for a block type.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow.
   *
   * @return array
   *   Permissions available for the given block type.
   */
  protected function buildPermissions(WorkflowInterface $workflow): array {
    $transitions = $workflow->getTransitions();
    $workflow_id = $workflow->getId();
    $return = [];
    foreach ($transitions as $transition_id => $transition) {
      $return["perform $workflow_id workflow $transition_id transition"] = [
        'title' => $this->t('Perform %workflow_name workflow: %transition_name transition', [
          '%workflow_name' => $workflow->getLabel(),
          '%transition_name' => $transition->getLabel(),
        ]),
      ];
    }
    return $return;
  }

}
