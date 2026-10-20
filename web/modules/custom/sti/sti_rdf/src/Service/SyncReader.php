<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;

/**
 * Reads rdf_sync mappings from bundle and field config.
 */
final class SyncReader implements SyncReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function syncedBundles(array $entityTypeIds): array {
    $out = [];
    foreach ($entityTypeIds as $entityTypeId) {
      $bundleEntity = $this->bundleEntityType($entityTypeId);
      if ($bundleEntity === NULL) {
        continue;
      }
      foreach ($this->entityTypeManager->getStorage($bundleEntity)->loadMultiple() as $bundle) {
        if (!$bundle instanceof ConfigEntityInterface) {
          continue;
        }
        $type = $bundle->getThirdPartySetting('rdf_sync', 'type');
        if (!is_string($type) || $type === '') {
          continue;
        }
        $out[] = [
          'entity_type' => $entityTypeId,
          'bundle' => (string) $bundle->id(),
          'label' => (string) $bundle->label(),
          'type' => $type,
        ];
      }
    }
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSyncs(string $entityTypeId, string $bundle): array {
    $bundleEntityType = $this->bundleEntityType($entityTypeId);
    $bundleEntity = $bundleEntityType ? $this->entityTypeManager->getStorage($bundleEntityType)->load($bundle) : NULL;
    if (!$bundleEntity instanceof ConfigEntityInterface) {
      return [];
    }

    $definitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);
    $sources = [];
    foreach ((array) $bundleEntity->getThirdPartySetting('rdf_sync', 'fields', []) as $fieldName => $columns) {
      $sources[] = [(string) $fieldName, (array) $columns];
    }
    foreach ($definitions as $fieldName => $definition) {
      if ($definition instanceof FieldConfigInterface && $definition->getThirdPartySettings('rdf_sync')) {
        $sources[] = [(string) $fieldName, $definition->getThirdPartySettings('rdf_sync')];
      }
    }

    $out = [];
    foreach ($sources as [$fieldName, $columns]) {
      $definition = $definitions[$fieldName] ?? NULL;
      foreach ($columns as $column => $property) {
        if (!is_array($property) || empty($property['predicate'])) {
          continue;
        }
        $out[] = [
          'field' => $fieldName,
          'column' => (string) $column,
          'predicate' => (string) $property['predicate'],
          'type' => isset($property['type']) ? (string) $property['type'] : NULL,
          'label' => $definition ? (string) $definition->getLabel() : $fieldName,
          'description' => $definition ? trim(strip_tags((string) $definition->getDescription())) : '',
        ];
      }
    }
    return $out;
  }

  /**
   * Returns the bundle config entity type of a content entity type.
   */
  private function bundleEntityType(string $entityTypeId): ?string {
    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return NULL;
    }
    return $this->entityTypeManager->getDefinition($entityTypeId)->getBundleEntityType();
  }

}
