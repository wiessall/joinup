<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
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
  public function defaultConfiguration(): array {
    return ['path' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(FieldItemListInterface $fieldItemList, EuOssCatalogueFetchedSolution $fetchedSolution): mixed {
    $path = explode('/', $this->getConfiguration()['path']);
    $data = $fetchedSolution->getData();
    $value = (array) NestedArray::getValue($data, $path);
    $fieldItemList->setValue($value);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

}
