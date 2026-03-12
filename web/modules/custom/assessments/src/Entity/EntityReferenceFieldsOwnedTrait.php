<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Trait for managing owned entity reference fields and act on update/delete.
 */
trait EntityReferenceFieldsOwnedTrait {

  /**
   * Returns ER fields whose entities are owned by this parent entity.
   *
   * @return string[]
   *   Array of field names.
   */
  abstract protected function getEntityReferenceFieldsOwned(): array;

  /**
   * Acts on parent entity update.
   *
   * Deletes entities that were removed from owned ER fields.
   */
  protected function onUpdateEntityReferenceFieldsOwned(): void {
    if (!$this->original instanceof ContentEntityInterface) {
      return;
    }

    $fieldNames = $this->getEntityReferenceFieldsOwned();
    foreach ($fieldNames as $fieldName) {
      if (!$this->hasField($fieldName) || !$this->original->hasField($fieldName)) {
        continue;
      }

      // Get current and original entity IDs.
      $currentIds = $this->getEntityReferenceFieldTargetIds($this, $fieldName);
      $originalIds = $this->getEntityReferenceFieldTargetIds($this->original, $fieldName);

      // Find IDs that were removed.
      $removedIds = array_diff($originalIds, $currentIds);

      if (empty($removedIds)) {
        continue;
      }

      // Delete removed entities.
      $fieldDefinition = $this->getFieldDefinition($fieldName);
      $targetType = $fieldDefinition->getSetting('target_type');
      $entityStorage = $this->entityTypeManager()->getStorage($targetType);
      $entities = $entityStorage->loadMultiple($removedIds);
      $entityStorage->delete($entities);
    }
  }

  /**
   * Acts on parent entity delete.
   *
   * Deletes all entities referenced in owned ER fields.
   */
  protected function onDeleteEntityReferenceFieldsOwned(): void {
    if ($this->isNew()) {
      return;
    }

    $fieldNames = $this->getEntityReferenceFieldsOwned();
    foreach ($fieldNames as $fieldName) {
      if (!$this->hasField($fieldName)) {
        continue;
      }

      $itemList = $this->get($fieldName);
      \assert($itemList instanceof EntityReferenceFieldItemListInterface);

      foreach ($itemList->referencedEntities() as $referencedEntity) {
        $referencedEntity->delete();
      }
    }
  }

  /**
   * Gets entity IDs referenced in a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $fieldName
   *   The field name.
   *
   * @return int[]
   *   Array of entity IDs.
   */
  protected function getEntityReferenceFieldTargetIds(ContentEntityInterface $entity, string $fieldName): array {
    $ids = [];

    if (!$entity->hasField($fieldName)) {
      return $ids;
    }

    $itemList = $entity->get($fieldName);
    \assert($itemList instanceof EntityReferenceFieldItemListInterface);

    foreach ($itemList as $item) {
      if (!empty($item->target_id)) {
        $ids[] = $item->target_id;
      }
    }

    return $ids;
  }

}
