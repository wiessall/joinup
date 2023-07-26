<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\node\Entity\Node;

/**
 * Bundle class for 'oss_solution' nodes.
 */
class OssSolution extends Node implements OssSolutionInterface {

  /**
   * {@inheritdoc}
   */
  public function addProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    /** @var \Drupal\pluginreference\Plugin\Field\FieldType\PluginReferenceFieldItemListInterface $source */
    $source = $this->get('oss_source');
    foreach ($source->getValue() as $value) {
      if ($value['plugin_id'] === $provider->getPluginId()) {
        return $this;
      }
    }
    $source->appendItem($provider->getPluginId());
    return $this;
  }

}
