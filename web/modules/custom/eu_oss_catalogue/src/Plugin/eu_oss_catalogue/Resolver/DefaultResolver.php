<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\PluginBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface;

/**
 * Default and fallback publiccode.yml value resolver.
 *
 * @EuOssCatalogueResolver(
 *   id = "default",
 *   label = "Default",
 * )
 */
class DefaultResolver extends PluginBase implements EuOssCatalogueResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(EuOssCatalogueFetchedSolution $fetched, string $fieldName): mixed {
    $path = explode('/', $this->configuration['path']);
    $data = $fetched->data;
    return NestedArray::getValue($data, $path);
  }

}
