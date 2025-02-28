<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\GroupTypeManagerInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Dynamic permissions provider.
 */
class JoinupGroupPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'og.group_type_manager')]
    protected readonly GroupTypeManagerInterface $groupTypeManager,
    #[Autowire(service: 'plugin.manager.workflow')]
    protected readonly WorkflowManagerInterface $workflowManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns an array of group ownership permissions.
   *
   * @return array[]
   *   Dynamically generated permissions.
   */
  public function groupOwnershipPermissions(): array {
    $permissions = [];
    $bundle_ids = $this->groupTypeManager->getGroupBundleIdsByEntityType('node');
    $bundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple($bundle_ids);
    foreach ($bundles as $bundle_id => $bundle) {
      $permissions["administer $bundle_id ownership"] = [
        'title' => $this->t('Administer @plural_label ownership', ['@plural_label' => $bundle->getPluralLabel()]),
        'description' => $this->t('Allows users granted with this permission to transfer the @singular_label ownership.', ['@singular_label' => $bundle->getSingularLabel()]),
      ];
    }

    return $permissions;
  }

  /**
   * Returns an array of announcements transition permissions.
   *
   * @return array[]
   *   Dynamically generated permissions.
   */
  public function announcementsPermissions(): array {
    $permissions = [];

    /** @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow */
    $workflow = $this->workflowManager->createInstance('announcement');
    foreach ($workflow->getTransitions() as $transition) {
      foreach (['own', 'any'] as $ownership) {
        $name = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, $ownership);
        $permissions[$name] = [
          'title' => $this->t('Perform @workflow workflow @transition transition on @ownership entity', [
            '%workflow' => $workflow->getLabel(),
            '%transition' => $transition->getLabel(),
            '%ownership' => $ownership,
          ]),
        ];
      }
    }

    return $permissions;
  }

}
