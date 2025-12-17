<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\assessments\DependentEntityManager;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Bundle class for binding_requirement paragraphs.
 */
class BindingRequirementParagraph extends Paragraph {

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    if ($update && $this->original instanceof BindingRequirementParagraph) {
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
    return ['expression', 'affects', 'has_participation', 'requirement_documents'];
  }

}
