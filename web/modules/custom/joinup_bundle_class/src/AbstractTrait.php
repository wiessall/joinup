<?php

declare(strict_types=1);

namespace Drupal\joinup_bundle_class;

/**
 * Reusable methods for entities that have a abstract field.
 */
trait AbstractTrait {

  /**
   * {@inheritdoc}
   */
  public function getAbstractAsRenderArray($display_options = []): array {
    assert(method_exists($this, 'getMainPropertyValue'), __TRAIT__ . ' depends on JoinupBundleClassFieldAccessTrait. Please include it in your class.');
    return $this->get($this->getAbstractField())->view($display_options);
  }

}
