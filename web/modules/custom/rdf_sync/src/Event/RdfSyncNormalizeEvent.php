<?php

declare(strict_types=1);

namespace Drupal\rdf_sync\Event;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Occurs after an entity has been normalized with rdf_sync.normalizer.
 *
 * Subscribers are able to alter the array before is returned by the normalizer.
 */
class RdfSyncNormalizeEvent extends Event {

  /**
   * Constructs a new event class instance.
   *
   * @param array $normalizedArray
   *   The normalized representation of the entity.
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity being normalized.
   */
  public function __construct(
    protected array $normalizedArray,
    protected RdfSyncEntityInterface $entity,
  ) {}

  /**
   * Returns the normalized representation of the entity.
   *
   * @return array
   *   The normalized representation of the entity.
   */
  public function getNormalizedArray(): array {
    return $this->normalizedArray;
  }

  /**
   * Sets the normalized representation of the entity.
   *
   * @param array $normalizedArray
   *   The normalized representation of the entity.
   *
   * @return $this
   */
  public function setNormalizedArray(array $normalizedArray): self {
    $this->normalizedArray = $normalizedArray;
    return $this;
  }

  /**
   * Returns the entity being normalized.
   *
   * @return \Drupal\rdf_sync\Entity\RdfSyncEntityInterface
   *   The entity being normalized.
   */
  public function getEntity(): RdfSyncEntityInterface {
    return $this->entity;
  }

}
