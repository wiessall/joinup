<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueResolver;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Resolver that converts markdown to HTML.
 */
#[EuOssCatalogueResolver(
  id: "markdown",
  label: new TranslatableMarkup("Markdown")
)]
class MarkdownResolver extends DefaultResolver implements EuOssCatalogueResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    parent::resolve($value, $project, $metadata, $fieldItemList);

    foreach ($value as $langcode => $items) {
      foreach ($items as $delta => $item) {
        $value[$langcode][$delta] = [
          'value' => $item,
          'format' => 'markdown',
        ];
      }
    }
  }

}
