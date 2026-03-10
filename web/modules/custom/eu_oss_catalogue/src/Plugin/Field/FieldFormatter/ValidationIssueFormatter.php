<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Markup;
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
        '#theme' => 'validation_issue',
        '#type' => $item->type,
        '#key' => Markup::create(Html::escape($item->key)),
        '#line' => (int) $item->line,
        '#column' => (int) $item->column,
        '#description' => Markup::create(Html::escape($item->description)),
      ];
    }

    return $elements;
  }

}
