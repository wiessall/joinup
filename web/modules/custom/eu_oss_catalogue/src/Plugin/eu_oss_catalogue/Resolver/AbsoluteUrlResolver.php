<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;

/**
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
  public function resolve(EuOssCatalogueFetchedSolution $fetched, string $fieldName): mixed {
    $paths = (array) parent::resolve($fetched, $fieldName);
    foreach ($paths as &$path) {
      if (!preg_match('@^https?://@', $fetched->url)) {
        $path = $fetched->url . '/' . ltrim($path, '/');
      }
    }
    return $paths;
  }

}
