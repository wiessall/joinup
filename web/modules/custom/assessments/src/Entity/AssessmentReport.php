<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
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
    // Only proceed if the report is published.
    if (!$this->isPublished()) {
      return;
    }

    // Collect unpublished child entities that need to be published, keyed by
    // entity ID to avoid duplicates.
    $entitiesToPublish = [];

    // Collect unpublished providers.
    $this->collectUnpublished($entitiesToPublish, $this->get('provider')->referencedEntities());

    // Binding requirements are paragraphs. There is nothing to publish there,
    // but we need to go through them to get to other entities.
    foreach ($this->get('binding_requirement')->referencedEntities() as $bindingRequirementEntity) {
      $this->collectUnpublished($entitiesToPublish, $bindingRequirementEntity->get('expression')->referencedEntities());
      foreach ($bindingRequirementEntity->get('asset')->referencedEntities() as $entity) {
        if ($entity->getGroup()->uuid() === AssessmentInterface::SOLUTION_COLLECTOR && !$entity->isPublished()) {
          $entitiesToPublish[$entity->id()] = $entity;
        }
      }
      $this->collectUnpublished($entitiesToPublish, $bindingRequirementEntity->get('affects')->referencedEntities());
      $this->collectUnpublished($entitiesToPublish, $bindingRequirementEntity->get('has_participation')->referencedEntities());
    }

    // Publish the collected entities.
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
   * Collects unpublished entities into the given array, keyed by entity ID.
   *
   * @param \Drupal\Core\Entity\EntityPublishedInterface[] $collect
   *   The array to collect into, passed by reference.
   * @param \Drupal\Core\Entity\EntityPublishedInterface[] $entities
   *   The entities to filter.
   */
  protected function collectUnpublished(array &$collect, array $entities): void {
    foreach ($entities as $entity) {
      if (!$entity->isPublished()) {
        $collect[$entity->id()] = $entity;
      }
    }
  }

}
