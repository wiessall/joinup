<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dynamic permissions provider.
 */
class
StateMachinePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a state machine permissions object.
   *
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The workflow manager.
   */
  public function __construct(protected WorkflowManagerInterface $workflowManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.workflow'));
  }

  /**
   * Returns an array of permissions related to state machine.
   *
   * @return array
   *   Dynamically generated permissions.
   */
  public function stateMachinePermissions(): array {
    $workflowDefinitions = $this->workflowManager->getDefinitions();
    $permissions = [];

    foreach ($workflowDefinitions as $workflowDefinition) {
      $transitions = $workflowDefinition->getTransitions();
      $workflow_id = $workflowDefinition->getId();
      foreach ($transitions as $transition_id => $transition) {
        foreach (['own', 'any'] as $ownership) {
          $permissions["perform $workflow_id workflow $transition_id transition on $ownership entity"] = [
            'title' => $this->t('Perform %workflow_name workflow: %transition_name transition', [
              '%workflow_name' => $workflowDefinition->getLabel(),
              '%transition_name' => $transition->getLabel(),
            ]),
          ];
        }
      }
    }

    return $permissions;
  }

}
