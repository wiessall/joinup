<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\ConfigReadOnlyTrait;

/**
 * Behat context to clean up bundle entity types created during tests.
 */
class ContentTypeContext extends RawDrupalContext {

  use ConfigReadOnlyTrait;

  /**
   * List of entity bundle IDs before the test grouped by bundle entity type ID.
   *
   * @var string[][]
   */
  protected array $bundleTypeIds = [];

  /**
   * Creates a snapshot about the available bundle entity type config ID.
   *
   * @BeforeScenario @content_type
   */
  public function takeNodeTypeSnapshot(): void {
    self::bypassReadOnlyConfig();
    $this->bundleTypeIds = $this->getEntityBundleTypes();
  }

  /**
   * Deletes new bundle entity type configurations.
   *
   * @AfterScenario @content_type
   */
  public function cleanupTestNodeTypes(): void {
    $entityTypeManager = \Drupal::entityTypeManager();

    foreach ($this->getEntityBundleTypes() as $bundleEntityType => $bundleTypeIds) {
      $idsToDelete = [];

      foreach ($bundleTypeIds as $bundleTypeId) {
        if (in_array($bundleTypeId, $this->bundleTypeIds[$bundleEntityType], TRUE)) {
          continue;
        }
        $idsToDelete[] = $bundleTypeId;
      }

      if ($idsToDelete) {
        $storage = $entityTypeManager->getStorage($bundleEntityType);
        $storage->delete($storage->loadMultiple($idsToDelete));
      }
    }

    // Restore config readonly status.
    self::restoreReadOnlyConfig();
  }

  /**
   * The currently available entity bundle IDs, keyed by the bundle entity type.
   *
   * @return string[][]
   *   The currently available entity bundle IDs, keyed by the bundle entity
   *   type ID.
   */
  protected function getEntityBundleTypes(): array {
    $entityTypeManager = \Drupal::entityTypeManager();

    $types = array_reduce(
      $entityTypeManager->getDefinitions(),
      function (array $carry, EntityTypeInterface $definition) {
        if ($definition->getBundleOf()) {
          $carry[$definition->id()] = [];
        }
        return $carry;
      },
      []
    );

    foreach (array_keys($types) as $bundleTypeId) {
      $types[$bundleTypeId] = array_keys(
        $entityTypeManager->getStorage($bundleTypeId)->loadMultiple()
      );
    }

    return $types;
  }

}
