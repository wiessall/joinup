<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

/**
 * Common tasks for testing entity creation and cleanup.
 */
trait TestingEntitiesTrait {

  use SearchTrait;

  /**
   * Testing entities.
   *
   * Associative array keyed by entity type IDs and having a list of testing
   * entity instances as values. Each list is keyed by entity ID.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[][]
   */
  protected $entities = [];

  /**
   * Remove any created test entities.
   *
   * @AfterScenario
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when any of the test entities could not be deleted.
   */
  public function cleanTestingEntities(): void {
    if (empty($this->entities)) {
      return;
    }

    foreach ($this->entities as $entity_type_id => $entities) {
      if (!empty($entities)) {
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
        // We reload entities to account changes made during the test.
        foreach ($storage->loadMultiple(array_keys($entities)) as $entity) {
          $entity->skip_notification = TRUE;
          @$entity->delete();
        }
      }
    }
    $this->entities = [];
  }

}
