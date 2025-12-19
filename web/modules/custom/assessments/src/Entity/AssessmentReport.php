<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;

/**
 * Bundle class for assessment reports.
 */
class AssessmentReport extends Assessment implements AssessmentInterface, EntityWorkflowStateInterface {

  use EntityReferenceFieldsOwnedTrait;
  use EntityWorkflowStateTrait;
  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'state';
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    if ($update) {
      $this->onUpdateEntityReferenceFieldsOwned();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);

    foreach ($entities as $entity) {
      \assert($entity instanceof static);
      $entity->onDeleteEntityReferenceFieldsOwned();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityReferenceFieldsOwned(): array {
    return ['legal', 'organisational', 'semantic', 'technical', 'documents', 'binding_requirement'];
  }

}
