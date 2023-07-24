<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;

/**
 * Converts a path relative to solution code repository to an absolute URL.
 *
 * @EuOssCatalogueResolver(
 *   id = "absolute_url",
 *   label = "Absolute URL",
 * )
 */
class AbsoluteUrlResolver extends DefaultResolver {

  /**
   * {@inheritdoc}
   */
  public function resolve(EuOssCatalogueFetchedSolution $fetchedSolution, string $fieldName): mixed {
    $paths = (array) parent::resolve($fetchedSolution, $fieldName);
    foreach ($paths as &$path) {
      if (!preg_match('@^https?://@', $path)) {
        $path = $fetchedSolution->getUrl() . '/' . ltrim($path, '/');
      }
    }
    return $paths;
  }

}
