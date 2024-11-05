<?php

declare(strict_types=1);

namespace Drupal\joinup_bundle_class;

/**
 * Reusable methods for entities that have a description field.
 */
trait DescriptionTrait {

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'description';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionAsRenderArray($display_options = []): array {
    assert(method_exists($this, 'getMainPropertyValue'), __TRAIT__ . ' depends on JoinupBundleClassFieldAccessTrait. Please include it in your class.');
    return $this->get($this->getDescriptionField())->view($display_options);
  }

}
