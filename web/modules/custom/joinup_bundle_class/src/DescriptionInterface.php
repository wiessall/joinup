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
   * @var string $view_mode
   *   The view mode.
   *
   * @return array
   *   The description, or NULL if the description is not set.
   */
  public function getDescription(string $view_mode): ?array;

  /**
   * Returns the main description field.
   *
   * @return string
   *   The field name.
   */
  public function getDescriptionField(): string;

}
