<?php

declare(strict_types=1);

namespace Drupal\rdf_sync;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for RDF URI generator plugins.
 */
interface RdfUriGeneratorPluginInterface {

  /**
   * Generates the RDF URI.
   *
   * @return string
   *   An entity ID.
   */
  public function generate(): string;

  /**
   * Sets the entity for which the URI is being generated.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return $this
   */
  public function setEntity(ContentEntityInterface $entity): self;

  /**
   * Gets the entity for which the URI is being generated.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface;

}
