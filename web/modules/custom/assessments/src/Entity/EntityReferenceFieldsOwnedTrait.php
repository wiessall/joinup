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

      $targetIds = $this->getEntityReferenceFieldTargetIds($this, $fieldName);
      if (empty($targetIds)) {
        continue;
      }

      $fieldDefinition = $this->getFieldDefinition($fieldName);
      $targetType = $fieldDefinition->getSetting('target_type');
      $entityStorage = $this->entityTypeManager()->getStorage($targetType);
      $entities = $entityStorage->loadMultiple($targetIds);
      $entityStorage->delete($entities);
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

    $entityId = $entity->id();
    $entityTypeId = $entity->getEntityTypeId();
    $tableName = "{$entityTypeId}__{$fieldName}";
    $columnName = "{$fieldName}_target_id";

    $database = \Drupal::database();
    foreach ($itemList as $item) {
      if (empty($item->target_id)) {
        continue;
      }

      $targetId = $item->target_id;

      // Check whether the table has references other than the entity ID.
      $count = (int) $database->select($tableName, 't')
        ->condition("t.{$columnName}", $targetId)
        ->condition('t.entity_id', $entityId, '<>')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($count === 0) {
        $ids[] = $targetId;
      }
    }

    return $ids;
  }

}
