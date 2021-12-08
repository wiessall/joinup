<?php

declare(strict_types = 1);

namespace Drupal\joinup_bundle_class;

/**
 * Interface for bundle classes that have a description field.
 */
interface DescriptionInterface {

  /**
   * Returns description.
   *
   * @return string|null
   *   The description, or NULL if the description is not set.
   */
  public function getDescription(): ?string;

}
