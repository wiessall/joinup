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
      $this->onUpdateReflectPublicationStatus();
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

  /**
   * Acts on parent entity update to reflect publication status.
   */
  protected function onUpdateReflectPublicationStatus(): void {
    // If the entity does not have publication status, do nothing.
    if (!method_exists($this, 'isPublished') || !method_exists($this, 'setPublished')) {
      return;
    }

    // If the publication status did not change, do nothing.
    if ($this->isPublished() === $this->original->isPublished()) {
      return;
    }

    // We need to collect the entities that should be updated.
    $entitiesToUpdate = [];
    $reportIsPublished = $this->isPublished();

    // Only keep providers that are to be published.
    $providerEntities = array_filter($this->get('provider')->referencedEntities(), function (AssessmentInterface $entity) use ($reportIsPublished): bool {
      // Only update assessments towards the published state because they
      // might later be referred to by other reports.
      return $reportIsPublished && !$entity->isPublished();
    });
    $entitiesToUpdate = array_merge($entitiesToUpdate, $providerEntities);

    // Binding requirements are paragraphs. There is nothing to publish.
    $bindingRequirementEntities = $this->get('binding_requirement')->referencedEntities();
    foreach ($bindingRequirementEntities as $bindingRequirementEntity) {
      // Get expressions, assets, public services, and participations.
      $expressions = $bindingRequirementEntity->get('expression')->referencedEntities();
      $entitiesToUpdate = array_merge($entitiesToUpdate, $expressions);
      $assets = $bindingRequirementEntity->get('asset')->referencedEntities();
      $assets = array_filter($assets, function (SolutionInterface $entity) use ($reportIsPublished): bool {
        // Only update solutions created through Assessments and only towards
        // the published state because they might later be referred to by other
        // reports.
        return $entity->getGroup()->uuid() === AssessmentInterface::SOLUTION_COLLECTOR
          && $reportIsPublished
          && !$entity->isPublished();
      });
      $publicServices = $bindingRequirementEntity->get('affects')->referencedEntities();
      $entitiesToUpdate = array_merge($entitiesToUpdate, $publicServices);
      $participations = $bindingRequirementEntity->get('has_participation')->referencedEntities();
      $entitiesToUpdate = array_merge($entitiesToUpdate, $participations);
    }

    // Update the publication status of collected entities.
    foreach ($entitiesToUpdate as $entity) {
      if ($entity instanceof EntityPublishedInterface && $entity->isPublished() !== $this->isPublished()) {
        match ($this->isPublished()) {
          TRUE => $entity->setPublished(),
          FALSE => $entity->setUnpublished(),
        };
        $entity->save();
      }
    }

    foreach ($assets as $asset) {
      $asset->setWorkflowState('published');
      $asset->skip_notification = TRUE;
      $asset->save();
    }
  }

}
