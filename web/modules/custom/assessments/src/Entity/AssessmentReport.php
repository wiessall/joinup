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
      $orphanCandidates = $entity->collectSolutionCollectorAssets();
      $entity->onDeleteEntityReferenceFieldsOwned();
      $entity->deleteOrphanedSolutionCollectorAssets($orphanCandidates);
    }
  }

  /**
   * Collects SOLUTION_COLLECTOR assets from the report's requirements.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface[]
   *   Solutions belonging to the SOLUTION_COLLECTOR group, keyed by node ID.
   */
  protected function collectSolutionCollectorAssets(): array {
    $solutions = [];
    foreach ($this->get('binding_requirement')->referencedEntities() as $bindingRequirement) {
      foreach ($bindingRequirement->get('asset')->referencedEntities() as $solution) {
        assert($solution instanceof SolutionInterface);
        if ($solution->getGroup()->uuid() === AssessmentInterface::SOLUTION_COLLECTOR) {
          $solutions[$solution->id()] = $solution;
        }
      }
    }
    return $solutions;
  }

  /**
   * Deletes orphaned SOLUTION_COLLECTOR solutions when no longer referenced.
   *
   * A solution is considered orphaned if no binding requirement paragraph
   * still references it via the asset field.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface[] $solutions
   *   Candidate solutions, keyed by node ID.
   */
  protected function deleteOrphanedSolutionCollectorAssets(array $solutions): void {
    $database = \Drupal::database();
    foreach ($solutions as $solution) {
      $count = (int) $database
        ->select('paragraph__asset', 'pa')
        ->condition('pa.asset_target_id', $solution->id())
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($count === 0) {
        $solution->delete();
      }
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

  /**
   * Returns the entity creation timestamp.
   *
   * @return int|null
   *   Creation timestamp of the entity, or NULL if unknown.
   */
  public function getCreatedTime(): int|null {
    $createdTime = $this->get('created')->value;
    return $createdTime !== NULL ? (int) $createdTime : NULL;
  }

}
