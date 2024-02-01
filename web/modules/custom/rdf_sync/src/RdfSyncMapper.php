<?php

declare(strict_types=1);

namespace Drupal\rdf_sync;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Model\ColumnMapping;

/**
 * RDF Sync mapper service.
 */
class RdfSyncMapper {

  /**
   * Constructs a new sync mapper instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {}

  /**
   * Returns the RDF type of given bundle.
   *
   * @param string $entityTypeId
   *   The bundle's entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return string|null
   *   The RDF type URI or NULL if not mapped.
   */
  public function getBundleRdfType(string $entityTypeId, string $bundle): ?string {
    return $this->getBundleMapping($entityTypeId, $bundle)['type'] ?? NULL;
  }

  /**
   * Checks whether a given bundle is mapped.
   *
   * @param string $entityTypeId
   *   The bundle's entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return bool
   *   Whether a given bundle is mapped.
   */
  public function isMappedBundle(string $entityTypeId, string $bundle): bool {
    return (bool) $this->getBundleRdfType($entityTypeId, $bundle);
  }

  /**
   * Returns a list of entity type IDs having at least one mapping.
   *
   * @return string[]
   *   A list of entity type IDs.
   */
  public function getEntityTypeIdsWithMappings(): array {
    $allBundleInfo = $this->entityTypeBundleInfo->getAllBundleInfo();
    return array_reduce(
      array_keys($allBundleInfo),
      function (array $entityTypeIds, string $entityTypeId) use ($allBundleInfo): array {
        foreach ($allBundleInfo[$entityTypeId] as $bundleInfo) {
          if (!empty($bundleInfo['rdf_sync'])) {
            $entityTypeIds[] = $entityTypeId;
            break;
          }
        }
        return $entityTypeIds;
      },
      [],
    );
  }

  /**
   * Returns the mappings of given bundle.
   *
   * @param string $entityTypeId
   *   The bundle's entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\rdf_sync\Model\ColumnMapping[][]
   *   Mappings list.
   */
  public function getMappings(string $entityTypeId, string $bundle): array {
    $mappings = [];
    foreach ($this->getBundleMapping($entityTypeId, $bundle)['fields'] ?? [] as $fieldName => $columns) {
      foreach ($columns as $columnName => $mapping) {
        $mappings[$fieldName][$columnName] = ColumnMapping::createFromArray($mapping);
      }
    }
    return $mappings;
  }

  /**
   * Returns the bundle RDF URI plugin ID.
   *
   * @param string $entityTypeId
   *   The bundle's entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array|null
   *   The bundle RDF URI plugin ID.
   */
  public function getRdfUriPluginId(string $entityTypeId, string $bundle): ?string {
    return $this->getBundleMapping($entityTypeId, $bundle)['uri_plugin'] ?? NULL;
  }

  /**
   * Checks whether an entity did map the bundle.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   Whether an entity did map the bundle.
   */
  public function hasBundleMapping(RdfSyncEntityInterface $entity): bool {
    $bundleKey = $entity->getEntityType()->getKey('bundle');
    $fieldMappings = $this->getMappings($entity->getEntityTypeId(), $entity->bundle());
    if (!isset($fieldMappings[$bundleKey])) {
      return FALSE;
    }
    $mainProperty = $entity->getFieldDefinition($bundleKey)->getFieldStorageDefinition()->getMainPropertyName();
    return isset($fieldMappings[$bundleKey][$mainProperty]);
  }

  /**
   * Returns the bundle mapping.
   *
   * @param string $entityTypeId
   *   The bundle's entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array|null
   *   The bundle mapping or NULL if no mapping is defined.
   */
  protected function getBundleMapping(string $entityTypeId, string $bundle): ?array {
    // Cover entity types without bundles.
    $bundle = $bundle ?: $entityTypeId;
    $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId)[$bundle] ?? [];
    if (!$bundleInfo) {
      return NULL;
    }
    return $bundleInfo['rdf_sync'] ?? [];
  }

}
