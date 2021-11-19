<?php

declare(strict_types = 1);

namespace Drupal\joinup_bundle_class;

/**
 * Reusable methods for entities that have a description field.
 */
trait DescriptionTrait {

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    assert(method_exists($this, 'getMainPropertyValue'), __TRAIT__ . ' depends on JoinupBundleClassFieldAccessTrait. Please include it in your class.');
    $value = $this->get($this->getDescriptionField())->view('search_result');
    return $value ? (string) $value[0]['#markup'] : NULL;
  }

}
