<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;

/**
 * Avoinkoodi catalogue provider.
 */
#[EuOssCatalogueProvider(
  id: 'avoinkoodi_fi',
  label: new TranslatableMarkup('Avoinkoodi.fi'),
  parser: 'publiccode_yml',
  type: CatalogueType::National,
  weblinks: [
    [
      'url' => 'http://avoinkoodi.fi',
      'title' => NULL,
    ],
  ],
  country: 'FI',
  description: new TranslatableMarkup('Avoinkoodi.fi catalogue lists together software used in Finnish government, education and the local government sector, whose source code has been published openly.'),
)]
class FinnishCatalogue extends UrlListCatalogueProviderBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'endpoints' => [
        [
          'apiUrl' => 'http://avoinkoodi.fi/projects.json',
          'rootKeys' => ['projects'],
          'urlKeys' => ['code_url'],
        ],
        [
          'apiUrl' => 'http://avoinkoodi.fi/municipalityprojects.json',
          'rootKeys' => ['municipalityprojects'],
          'urlKeys' => ['code_url'],
        ],
        [
          'apiUrl' => 'http://avoinkoodi.fi/eduprojects.json',
          'rootKeys' => ['eduprojects'],
          'urlKeys' => ['code_url'],
        ],
      ],
    ] + parent::defaultConfiguration();
  }

}
