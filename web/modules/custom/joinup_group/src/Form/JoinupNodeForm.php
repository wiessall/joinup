<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\node\NodeForm;
use Drupal\og\Og;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the group for node types that are group members.
 */
class JoinupNodeForm extends NodeForm {

  public function __construct(
    EntityRepositoryInterface $entity_repository,
    PrivateTempStoreFactory $temp_store_factory,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    AccountInterface $current_user,
    DateFormatterInterface $date_formatter,
    protected JoinupGroupManagerInterface $groupManager,
    protected EntityFieldManagerInterface $fieldManager,
  ) {
    parent::__construct($entity_repository, $temp_store_factory, $entity_type_bundle_info, $time, $current_user, $date_formatter);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity.repository'),
      $container->get('tempstore.private'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('joinup_group.group_manager'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity(): void {
    parent::prepareEntity();

    // This entity is not group content.
    if (!$this->entity instanceof GroupContentInterface) {
      return;
    }
    // Only set the group on new entities.
    if (!$this->entity->isNew()) {
      return;
    }
    // The group cannot be determined from context.
    if (!$group = $this->groupManager->getGroupFromContext()) {
      return;
    }

    $map = $this->fieldManager->getFieldMapByFieldType('og_standard_reference');
    foreach ($map as $entityTypeId => $info) {
      if ($entityTypeId !== $this->entity->getEntityTypeId()) {
        continue;
      }
      foreach ($info as $fieldType => ['bundles' => $bundles]) {
        foreach ($bundles as $bundle) {
          if ($bundle === $this->entity->bundle()) {
//            $this->entity->set($fieldType, $group);
//            return;
          }
        }
      }
    }
  }

}
