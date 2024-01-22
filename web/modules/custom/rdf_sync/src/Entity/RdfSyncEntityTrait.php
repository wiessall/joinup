<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync\Entity;

/**
 * Reusable methods for RdfSyncEntityInterface entities.
 */
trait RdfSyncEntityTrait {

  /**
   * {@inheritdoc}
   */
  public function getUri(): string {
    assert($this instanceof RdfSyncEntityInterface);
    return $this->get('uri')->first()->getValue()['value'];
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByUri(string $uri): ?RdfSyncEntityInterface {
    $entityTypeRepository = \Drupal::service('entity_type.repository');
    $entityTypeManager = \Drupal::entityTypeManager();
    $storage = $entityTypeManager->getStorage($entityTypeRepository->getEntityTypeFromClass(static::class));
    $entities = $storage->loadByProperties(['uri' => $uri]);
    if (!$entities) {
      return NULL;
    }
    return reset($entities);
  }

}
