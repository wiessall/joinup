<?php

declare(strict_types = 1);

namespace Drupal\custom_page;

/**
 * Landing page alias validator service.
 */
class LandingPageAliasValidator implements LandingPageAliasValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function isValid(string $alias): bool {
    $paths = [
      '/search',
      '/topic',
      '/contact',
      '/collection',
      '/user',
      '/dashboard',
      '/interoperable-europe',
    ];

    foreach ($paths as $path) {
      if ($alias === $path || str_starts_with($alias, $path . '/')) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
