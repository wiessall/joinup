<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for mapped entities.
 */
interface RdfSyncEntityInterface extends ContentEntityInterface {

  /**
   * Returns the RDF representation of the entity as a URI.
   *
   * @return string
   *   The URI.
   */
  public function getUri(): string;

  /**
   * Loads the entity by its RDF URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return static|null
   *   The entity or NULL if it cannot be determined.
   */
  public static function loadByUri(string $uri): ?RdfSyncEntityInterface;

}
