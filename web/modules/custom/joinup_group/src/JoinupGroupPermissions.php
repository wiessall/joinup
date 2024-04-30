<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\GroupTypeManagerInterface;
use Drupal\rdf_entity\Entity\RdfEntityType;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dynamic permissions provider.
 */
class JoinupGroupPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The OG group type manager.
   */
  protected GroupTypeManagerInterface $groupTypeManager;

  /**
   * The workflow manager service.
   */
  protected WorkflowManagerInterface $workflowManager;

  /**
   * Creates a new JoinupGroupPermissions object.
   *
   * @param \Drupal\og\GroupTypeManagerInterface $groupTypeManager
   *   The OG group type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager service.
   */
  public function __construct(GroupTypeManagerInterface $groupTypeManager, WorkflowManagerInterface $workflow_manager) {
    $this->groupTypeManager = $groupTypeManager;
    $this->workflowManager = $workflow_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('og.group_type_manager'),
      $container->get('plugin.manager.workflow'),
    );
  }

  /**
   * Returns an array of group ownership permissions.
   *
   * @return array[]
   *   Dynamically generated permissions.
   */
  public function groupOwnershipPermissions(): array {
    $permissions = [];
    $bundle_ids = $this->groupTypeManager->getGroupBundleIdsByEntityType('rdf_entity');
    /** @var \Drupal\rdf_entity\RdfEntityTypeInterface[] $bundles */
    $bundles = RdfEntityType::loadMultiple($bundle_ids);
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
