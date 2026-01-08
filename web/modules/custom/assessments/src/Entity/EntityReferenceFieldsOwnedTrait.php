<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
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

    $field_names = $this->getEntityReferenceFieldsOwned();
    foreach ($field_names as $field_name) {
      if (!$this->hasField($field_name) || !$this->original->hasField($field_name)) {
        continue;
      }

      // Get current and original entity IDs.
      $current_ids = $this->getEntityReferenceFieldTargetIds($this, $field_name);
      $original_ids = $this->getEntityReferenceFieldTargetIds($this->original, $field_name);

      // Find IDs that were removed.
      $removed_ids = array_diff($original_ids, $current_ids);

      if (empty($removed_ids)) {
        continue;
      }

      // Delete removed entities.
      $field_definition = $this->getFieldDefinition($field_name);
      $target_type = $field_definition->getSetting('target_type');
      $entity_storage = $this->entityTypeManager()->getStorage($target_type);
      $entities = $entity_storage->loadMultiple($removed_ids);
      $entity_storage->delete($entities);
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

    $field_names = $this->getEntityReferenceFieldsOwned();
    foreach ($field_names as $field_name) {
      if (!$this->hasField($field_name)) {
        continue;
      }

      $item_list = $this->get($field_name);
      \assert($item_list instanceof EntityReferenceFieldItemListInterface);

      foreach ($item_list->referencedEntities() as $referenced_entity) {
        $referenced_entity->delete();
      }
    }
  }

  /**
   * Gets entity IDs referenced in a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   *
   * @return int[]
   *   Array of entity IDs.
   */
  protected function getEntityReferenceFieldTargetIds(ContentEntityInterface $entity, string $field_name): array {
    $ids = [];

    if (!$entity->hasField($field_name)) {
      return $ids;
    }

    $item_list = $entity->get($field_name);
    \assert($item_list instanceof EntityReferenceFieldItemListInterface);

    foreach ($item_list as $item) {
      if (!empty($item->target_id)) {
        $ids[] = $item->target_id;
      }
    }

    return $ids;
  }

}
