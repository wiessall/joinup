<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;

/**
 * Provider plugin of the Swedish software catalogue.
 */
#[EuOssCatalogueProvider(
  id: 'offentligkod_se',
  label: new TranslatableMarkup('Offentligkod.se'),
  parser: 'publiccode_yml',
  type: CatalogueType::National,
  weblinks: [
    [
      'url' => 'https://offentligkod.se',
      'title' => NULL,
    ],
  ],
  country: 'SE',
  description: new TranslatableMarkup('Offentlig Kod Swedish National Catalogue advocates open-source solutions for public sector IT, sharing reusable code and fostering collaboration & transparency in government projects.'),
)]
class OffentligkodSe extends UrlListProviderBase {

  /**
   * {@inheritdoc}
   */
  protected string|array $rootKeys = 'katalog';

  /**
   * {@inheritdoc}
   */
  protected string|array $urlKeys = 'url';


  /**
   * {@inheritdoc}
   */
  protected const array ENDPOINTS = [
    'https://offentligkod.se/api/',
  ];

}
