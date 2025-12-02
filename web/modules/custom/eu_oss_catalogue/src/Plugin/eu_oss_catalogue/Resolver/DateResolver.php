<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueResolver;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Date resolver that converts timestamps to date strings.
 *
 * This resolver extends DefaultResolver and handles the case where YAML parser
 * converts unquoted ISO date strings (e.g., "2025-11-17") to Unix timestamps.
 * It detects integer timestamps and converts them back to ISO date format.
 */
#[EuOssCatalogueResolver(
  id: "date",
  label: new TranslatableMarkup("Date")
)]
class DateResolver extends DefaultResolver implements EuOssCatalogueResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    parent::resolve($value, $project, $metadata, $fieldItemList);

    foreach ($value as $langcode => $values) {
      foreach ($values as $key => $item) {
        if (!is_int($item)) {
          continue;
        }
        $value[$langcode][$key] = date('Y-m-d', $item);
      }
    }
  }

}
