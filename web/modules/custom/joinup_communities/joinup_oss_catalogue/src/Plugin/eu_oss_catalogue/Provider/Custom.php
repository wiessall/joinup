<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\UrlListCatalogueProviderBase;
use Drupal\joinup_oss_catalogue\Plugin\Deriver\CustomCatalogueDeriver;

/**
 * Custom catalogue provider for JSON-based configurations.
 */
#[EuOssCatalogueProvider(
  id: 'custom',
  label: new TranslatableMarkup('Custom'),
  parser: 'publiccode_yml',
  type: CatalogueType::European,
  description: new TranslatableMarkup('Custom catalogue provider that reads from JSON files.'),
  deriver: CustomCatalogueDeriver::class,
)]
class Custom extends UrlListCatalogueProviderBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $json_data = $this->getPluginDefinition()['json_data'] ?? [];

    if (empty($json_data['solutions'])) {
      return parent::defaultConfiguration();
    }

    $endpoints = [
      [
        'apiUrl' => $this->getPluginDefinition()['json_file'],
        'rootKeys' => ['solutions'],
        'urlKeys' => ['url'],
      ],
    ];

    return [
        'endpoints' => $endpoints,
      ] + parent::defaultConfiguration();
  }

}
