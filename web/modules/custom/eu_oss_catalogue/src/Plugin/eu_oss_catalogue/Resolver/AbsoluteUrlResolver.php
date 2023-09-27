<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\eu_oss_catalogue\Model\FetchedSolution;

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
  public function resolve(FieldItemListInterface $fieldItemList, FetchedSolution $fetchedSolution): array {
    $paths = (array) parent::resolve($fieldItemList, $fetchedSolution);
    foreach ($paths as &$path) {
      if (!preg_match('@^https?://@', $path)) {
        $path = $fetchedSolution->url . '/' . ltrim($path, '/');
      }
    }
    $fieldItemList->setValue($paths);
    return $paths;
  }

}
