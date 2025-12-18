<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Dynamic permissions provider.
 */
class StateMachinePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.workflow')]
    protected readonly WorkflowManagerInterface $workflowManager,
  ) {}

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
      $workflowDefinition = $this->workflowManager->createInstance($workflowDefinition['id']);
      $transitions = $workflowDefinition->getTransitions();
      $workflow_id = $workflowDefinition->getId();
      foreach ($transitions as $transition_id => $transition) {
        foreach (['own', 'any'] as $ownership) {
          $permissions["perform $workflow_id workflow $transition_id transition on $ownership entity"] = [
            'title' => $this->t('Perform %workflow_name workflow: %transition_name transition on %ownership entity', [
              '%workflow_name' => $workflowDefinition->getLabel(),
              '%transition_name' => $transition->getLabel(),
              '%ownership' => $this->t('@entity-ownership', ['@entity-ownership' => $ownership], ['context' => 'own or any (entity)']),
            ]),
          ];
        }
      }
    }

    return $permissions;
  }

}
