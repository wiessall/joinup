<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueResolver;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Publiccode YML content resolver.
 */
#[EuOssCatalogueResolver(
  id: "publiccode_yml_content",
  label: new TranslatableMarkup("Publiccode YML content")
)]
class PubliccodeYmlContentResolver extends DefaultResolver implements EuOssCatalogueResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    // Store the original YAML content to preserve formatting and data types.
    // Avoids issues like date strings being converted to timestamps.
    $value[LanguageInterface::LANGCODE_DEFAULT] = [$project->getPubliccodeYml()];
  }

}
