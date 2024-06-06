<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Event\PermissionEventInterface as OgPermissionEventInterface;
use Drupal\og\GroupContentOperationPermission;
use Drupal\og\GroupPermission;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Drupal\state_machine_permissions\StateMachinePermissionsHelperInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides permissions related to og and state machine.
 */
class PermissionGenerationSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The permissions helper service.
   *
   * @var \Drupal\state_machine_permissions\StateMachinePermissionsHelperInterface
   */
  protected $permissionsHelper;

  /**
   * Creates a StateMachineOgSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\state_machine_permissions\StateMachinePermissionsHelperInterface $permissions_helper
   *   The permissions helper service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, StateMachinePermissionsHelperInterface $permissions_helper) {
    $this->entityFieldManager = $entity_field_manager;
    $this->permissionsHelper = $permissions_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OgPermissionEventInterface::EVENT_NAME => [
        ['ogGroupStateMachinePermissionProvider'],
        ['ogTransitionPermissionProvider'],
      ],
    ];
  }

  /**
   * Declares state machine permissions for the group itself.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function ogGroupStateMachinePermissionProvider(OgPermissionEventInterface $event): void {
    // Check if the group has a state field.
    $workflows = $this->permissionsHelper->getPossibleWorkflowsForBundle($event->getGroupEntityTypeId(), $event->getGroupBundleId());
    $permissions = [];
    foreach ($workflows as $workflow) {
      $transitions = $workflow->getTransitions();
      foreach ($transitions as $transition) {
        foreach (['own', 'any'] as $ownership) {
          $permission_string = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, $ownership);
        }
        $permissions[$permission_string] = new GroupPermission([
          'name' => $permission_string,
          'title' => $this->t('Perform "@workflow" workflow @transition transition on @ownership entity', [
            '@workflow' => $workflow->getLabel(),
            '@transition' => $transition->getLabel(),
            '@ownership' => $ownership,
          ]),
        ]);
      }
    }
    $event->setPermissions($permissions);
  }

  /**
   * Generate group content state update permissions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function ogTransitionPermissionProvider(OgPermissionEventInterface $event): void {
    foreach ($event->getGroupContentBundleIds() as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $workflows = $this->permissionsHelper->getPossibleWorkflowsForBundle($entity_type_id, $bundle, []);
        $permissions = [];
        foreach ($workflows as $workflow) {
          $transitions = $workflow->getTransitions();
          foreach ($transitions as $transition) {
            foreach (['own', 'any'] as $ownership) {
              $permission_string = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, $ownership);
              $permissions[$permission_string] = new GroupContentOperationPermission([
                'name' => $permission_string,
                'title' => $this->t('Perform "@workflow" workflow @transition transition on @ownership entity', [
                  '@workflow' => $workflow->getLabel(),
                  '@transition' => $transition->getLabel(),
                  '@ownership' => $ownership,
                ]),
                'entityType' => $entity_type_id,
                'bundle' => $bundle,
                'operation' => $permission_string,
                'owner' => $ownership === 'own',
              ]);
            }
          }
        }
        $event->setPermissions($permissions);
      }
    }
  }

}
