<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Service for managing dependent entity references.
 */
class DependentEntityManager {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Deletes entities that were removed from dependent reference fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $original
   *   The original entity before changes.
   * @param string[] $field_names
   *   The dependent reference field names.
   */
  public function deleteRemovedReferences(ContentEntityInterface $entity, ContentEntityInterface $original, array $field_names): void {
    foreach ($field_names as $field_name) {
      if (!$entity->hasField($field_name) || !$original->hasField($field_name)) {
        continue;
      }

      // Get current and original entity IDs.
      $current_ids = $this->getReferencedEntityIds($entity, $field_name);
      $original_ids = $this->getReferencedEntityIds($original, $field_name);

      // Find IDs that were removed.
      $removed_ids = array_diff($original_ids, $current_ids);

      if (empty($removed_ids)) {
        continue;
      }

      // Delete removed entities.
      $field_definition = $entity->getFieldDefinition($field_name);
      $target_type = $field_definition->getSetting('target_type');
      $entity_storage = $this->entityTypeManager->getStorage($target_type);
      $entities = $entity_storage->loadMultiple($removed_ids);
      $entity_storage->delete($entities);
    }
  }

  /**
   * Deletes all entities referenced in dependent fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string[] $field_names
   *   The dependent reference field names.
   */
  public function deleteDependentEntities(ContentEntityInterface $entity, array $field_names): void {
    foreach ($field_names as $field_name) {
      if (!$entity->hasField($field_name)) {
        continue;
      }

      $item_list = $entity->get($field_name);
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
  protected function getReferencedEntityIds(ContentEntityInterface $entity, string $field_name): array {
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
