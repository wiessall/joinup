<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_tmgmt\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\joinup_tmgmt\ExtractedDataMemo;

/**
 * Counts the values derived rather than taken from what is kept.
 */
class CountingExtractedDataMemo extends ExtractedDataMemo {

  /**
   * How often a value was derived, keyed by "entity type:ID".
   *
   * @var int[]
   */
  protected array $counts = [];

  /**
   * Returns how often a value was derived from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity the value is derived from.
   *
   * @return int
   *   The number of derivations.
   */
  public function derivations(ContentEntityInterface $entity): int {
    return $this->counts[$this->getCountKey($entity)] ?? 0;
  }

  /**
   * Forgets how often each value was derived.
   */
  public function resetDerivations(): void {
    $this->counts = [];
  }

  /**
   * {@inheritdoc}
   */
  public function remember(ContentEntityInterface $entity, callable $derive): array {
    return parent::remember($entity, function (ContentEntityInterface $entity) use ($derive): array {
      $key = $this->getCountKey($entity);
      $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;

      return $derive($entity);
    });
  }

  /**
   * Returns the key under which the derivations of a value are counted.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity the value is derived from.
   *
   * @return string
   *   The key.
   */
  protected function getCountKey(ContentEntityInterface $entity): string {
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

}
