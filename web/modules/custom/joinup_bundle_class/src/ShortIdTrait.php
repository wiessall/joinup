<?php

declare(strict_types=1);

namespace Drupal\joinup_bundle_class;

use Drupal\joinup_solution\Entity\SolutionInterface;

/**
 * Reusable methods for entities that have a short ID field.
 */
trait ShortIdTrait {

  /**
   * {@inheritdoc}
   */
  public function getShortId(): ?string {
    assert(method_exists($this, 'getMainPropertyValue'), __TRAIT__ . ' depends on ShortIdTrait. Please include it in your class.');
    // @todo The field will be merged with the `short_id` field in ISAICP-8574.
    $field_name = ($this instanceof SolutionInterface) ? 'short_id' : 'field_short_id';
    $value = $this->getMainPropertyValue($field_name);
    return $value ? (string) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasShortId(): bool {
    return (bool) $this->getShortId();
  }

}
