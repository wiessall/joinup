<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Entity;

/**
 * Reusable methods for RdfSyncEntityInterface entities.
 */
trait RdfSyncEntityTrait {

  /**
   * {@inheritdoc}
   */
  public function getUri(): string {
    assert($this instanceof RdfSyncEntityInterface);
    $fieldName = \Drupal::getContainer()->get('rdf_sync.mapper')->getRdfUriFieldName(entity: $this);
    return $this->get($fieldName)->value;
  }

  /**
   * Returns whether the entity has a URI.
   *
   * @return bool
   *   TRUE if the entity has a URI, FALSE otherwise.
   */
  public function hasUri(): bool {
    assert($this instanceof RdfSyncEntityInterface);
    $fieldName = \Drupal::getContainer()->get('rdf_sync.mapper')->getRdfUriFieldName(entity: $this);
    return !$this->get($fieldName)->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByUri(string $uri): ?RdfSyncEntityInterface {
    return \Drupal::getContainer()->get('rdf_sync.mapper')->getEntityByUri($uri);
  }

}
