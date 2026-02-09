<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_solution\Entity\SolutionInterface;
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
    $this->onSaveReflectPublicationStatus();
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

  /**
   * Publishes child entities when the report is published.
   */
  protected function onSaveReflectPublicationStatus(): void {
    if (!$this->isPublished()) {
      return;
    }

    // Collect unpublished child entities that need to be published.
    $entitiesToPublish = $this->collectUnpublishedEntities($this->get('provider')->referencedEntities());

    // Collect from binding requirements.
    foreach ($this->get('binding_requirement')->referencedEntities() as $bindingRequirement) {
      $entitiesToPublish += $this->collectUnpublishedEntities($bindingRequirement->get('expression')->referencedEntities());
      $entitiesToPublish += $this->collectUnpublishedEntities(array_filter($bindingRequirement->get('asset')->referencedEntities(), static fn(SolutionInterface $entity) => $entity->getGroup()->uuid() === AssessmentInterface::SOLUTION_COLLECTOR));
      $entitiesToPublish += $this->collectUnpublishedEntities($bindingRequirement->get('affects')->referencedEntities());
      $entitiesToPublish += $this->collectUnpublishedEntities($bindingRequirement->get('has_participation')->referencedEntities());
    }

    // Publish all collected entities.
    foreach ($entitiesToPublish as $entity) {
      if ($entity instanceof EntityWorkflowStateInterface) {
        $entity->setWorkflowState('published');
      }
      elseif ($entity instanceof EntityPublishedInterface) {
        $entity->setPublished();
      }

      $entity->skip_notification = TRUE;
      $entity->save();
    }
  }

  /**
   * Collect unpublished entities, keyed by UUID.
   */
  protected function collectUnpublishedEntities(array $entities): array {
    $result = [];
    foreach ($entities as $entity) {
      if (!$entity->isPublished()) {
        $result[$entity->uuid()] = $entity;
      }
    }
    return $result;
  }


}
