<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\DependentEntityManager;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;

/**
 * Bundle class for assessment reports.
 */
class AssessmentReport extends Assessment implements AssessmentInterface, EntityWorkflowStateInterface {

  use EntityWorkflowStateTrait;
  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'state';
  }

  /**
   * Returns the default state value based on user permissions.
   *
   * @return array
   *   The default value array.
   */
  public static function getDefaultStateValue(): array {
    // @todo ISAICP-10620: Remove/clarify this.
    $current_user = \Drupal::currentUser();
    $state = $current_user->hasPermission('bypass assessment report moderation')
      ? 'published'
      : 'draft';

    return [['value' => $state]];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    if ($update && $this->original instanceof AssessmentReport) {
      /** @var \Drupal\assessments\DependentEntityManager $dependent_entity_manager */
      $dependent_entity_manager = \Drupal::service(DependentEntityManager::class);
      $dependent_entity_manager->deleteRemovedReferences($this, $this->original, self::getDependentReferenceFields());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);

    foreach ($entities as $entity) {
      /** @var \Drupal\assessments\DependentEntityManager $dependent_entity_manager */
      $dependent_entity_manager = \Drupal::service(DependentEntityManager::class);
      $dependent_entity_manager->deleteDependentEntities($entity, self::getDependentReferenceFields());
    }
  }

  /**
   * Returns ER fields whose entities are deleted with this entity.
   *
   * @return string[]
   *   Array of field names.
   */
  public static function getDependentReferenceFields(): array {
    return ['legal', 'organisational', 'semantic', 'technical', 'documents', 'binding_requirement'];
  }

}
