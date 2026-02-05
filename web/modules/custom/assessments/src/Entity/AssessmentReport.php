<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

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
    // Only proceed if the report is published.
    if (!$this->isPublished()) {
      return;
    }

    // Collect unpublished child entities that need to be published.
    $entitiesToPublish = [];
    $assetsToPublish = [];

    // Collect unpublished providers.
    $providerEntities = array_filter($this->get('provider')->referencedEntities(), fn(AssessmentInterface $entity): bool => !$entity->isPublished());
    $entitiesToPublish = array_merge($entitiesToPublish, $providerEntities);

    // Binding requirements are paragraphs. There is nothing to publish there,
    // but we need to go through them to get to other entities.
    $bindingRequirementEntities = $this->get('binding_requirement')->referencedEntities();
    foreach ($bindingRequirementEntities as $bindingRequirementEntity) {
      $expressions = array_filter($bindingRequirementEntity->get('expression')->referencedEntities(), fn(AssessmentInterface $entity): bool => !$entity->isPublished());
      $entitiesToPublish = array_merge($entitiesToPublish, $expressions);

      $assets = array_filter($bindingRequirementEntity->get('asset')->referencedEntities(), fn(SolutionInterface $entity): bool => $entity->getGroup()->uuid() === AssessmentInterface::SOLUTION_COLLECTOR && !$entity->isPublished());
      $assetsToPublish = array_merge($assetsToPublish, $assets);

      $publicServices = array_filter($bindingRequirementEntity->get('affects')->referencedEntities(), fn(AssessmentInterface $entity): bool => !$entity->isPublished());
      $entitiesToPublish = array_merge($entitiesToPublish, $publicServices);

      $participations = array_filter($bindingRequirementEntity->get('has_participation')->referencedEntities(), fn(AssessmentInterface $entity): bool => !$entity->isPublished());
      $entitiesToPublish = array_merge($entitiesToPublish, $participations);
    }

    // Publish the collected entities.
    foreach ($entitiesToPublish as $entity) {
      $entity->setPublished();
      $entity->save();
    }

    foreach ($assetsToPublish as $asset) {
      $asset->setWorkflowState('published');
      $asset->skip_notification = TRUE;
      $asset->save();
    }
  }

}
