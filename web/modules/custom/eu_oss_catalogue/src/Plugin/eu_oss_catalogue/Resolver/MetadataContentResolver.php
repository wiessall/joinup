<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Metadata content resolver.
 *
 * @EuOssCatalogueResolver(
 *   id = "metadata_content",
 *   label = "Metadata content",
 * )
 */
class MetadataContentResolver extends DefaultResolver implements EuOssCatalogueResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    if (empty($metadata)) {
      $value = [];
    }
    else {
      $value[LanguageInterface::LANGCODE_DEFAULT] = [Yaml::encode($metadata)];
    }
  }

}
