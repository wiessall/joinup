<?php

declare(strict_types = 1);

namespace Drupal\custom_page;

/**
 * Provides an interface for the landing page alias validator service.
 */
interface LandingPageAliasValidatorInterface {

  /**
   * Checks if alias is valid.
   *
   * @param string $alias
   *   Alias.
   *
   * @return bool
   *   True if it is valid.
   */
  public function isValid(string $alias): bool;

}
