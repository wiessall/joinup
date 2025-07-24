<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;

/**
 * Belgian catalogue provider.
 */
#[EuOssCatalogueProvider(
  id: 'imio',
  label: new TranslatableMarkup('Open Code Belgium'),
  parser: 'publiccode_yml',
  type: CatalogueType::National,
  weblinks: [
    [
      'url' => 'https://www.imio.be',
      'title' => NULL,
    ],
  ],
  country: 'BE',
  description: new TranslatableMarkup('Catalogue of open source projects developed by/for federal, regional and local public administrations in Belgium.'),
)]
class BelgianCatalogue extends UrlListCatalogueProviderBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'endpoints' => [
          [
            'apiUrl' => 'https://raw.githubusercontent.com/Fedict/ospo/refs/heads/main/projects.json',
            'rootKeys' => ['projects'],
            'urlKeys' => ['code_url'],
          ],
      ],
    ] + parent::defaultConfiguration();
  }

}
