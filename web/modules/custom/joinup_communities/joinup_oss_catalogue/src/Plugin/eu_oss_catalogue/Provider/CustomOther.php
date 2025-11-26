<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\UrlListCatalogueProviderBase;

/**
 * Custom catalogue provider for JSON-based configurations.
 */
#[EuOssCatalogueProvider(
  id: 'custom:other',
  label: new TranslatableMarkup('Other'),
  parser: 'publiccode_yml',
  type: CatalogueType::European,
  description: new TranslatableMarkup('Open Source solutions that are not part of any national, regional or local catalogues but are present in EU OS Solutions Catalogue.'),
)]
class CustomOther extends UrlListCatalogueProviderBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'endpoints' => [
          [
            'apiUrl' => 'https://code.europa.eu/eu-oss-catalogue/conf/-/raw/main/projects.json',
            'rootKeys' => ['projects'],
            'urlKeys' => ['url'],
          ],
      ],
    ] + parent::defaultConfiguration();
  }

}
