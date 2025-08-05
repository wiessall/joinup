<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;

/**
 * French catalogue provider.
 */
#[EuOssCatalogueProvider(
  id: "awesome_code_gouv_fr",
  label: new TranslatableMarkup("Awesome CodeGouvFr"),
  parser: "publiccode_yml",
  type: CatalogueType::National,
  weblinks: [
    [
      'url' => 'https://code.gouv.fr/sources/#/awesome',
      'title' => NULL,
    ],
  ],
  country: "FR",
  description: new TranslatableMarkup("CodeGouvFr, the French National Catalogue with mission to use and contribute to free software and digital commons provides. Awesome Open Source projects actively maintained and funded by Public Administrations and Public Sector organizations."),
)]
class FrenchCatalogue extends UrlListCatalogueProviderBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'endpoints' => [
        [
          'apiUrl' => 'https://code.gouv.fr/data/awesome.json',
          'rootKeys' => ['projects'],
          'urlKeys' => ['code_url'],
        ],
      ],
    ] + parent::defaultConfiguration();
  }

}
