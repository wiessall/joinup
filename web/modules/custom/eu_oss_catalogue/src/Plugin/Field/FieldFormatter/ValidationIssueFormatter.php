<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Model\Validation\ValidationIssueType;

/**
 * Renders validation errors in [key line:N column:N] description format.
 */
#[FieldFormatter(
  id: 'validation_issue',
  label: new TranslatableMarkup('Validation issue'),
  field_types: ['validation_issue'],
)]
class ValidationIssueFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      if ($item->type !== ValidationIssueType::Error->value) {
        continue;
      }
      $elements[$delta] = [
        '#plain_text' => sprintf('[%s line:%d column:%d] %s', $item->key, (int) $item->line, (int) $item->column, $item->description),
      ];
    }

    return $elements;
  }

}
