<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Model\Validation\ValidationIssueType;

/**
 * Widget for manually entering or updating publiccode.yml validation issues.
 */
#[FieldWidget(
  id: 'validation_issue',
  label: new TranslatableMarkup('Validation issue'),
  field_types: ['validation_issue'],
)]
class ValidationIssueWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $item = $items[$delta];

    $element['#type'] = 'fieldset';

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        ValidationIssueType::Error->value => $this->t('Error'),
        ValidationIssueType::Warning->value => $this->t('Warning'),
      ],
      '#default_value' => $item->type ?? ValidationIssueType::Error->value,
    ];

    $element['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
      '#description' => $this->t('The publiccode.yml field key that triggered the issue.'),
      '#default_value' => $item->key ?? '',
      '#maxlength' => 255,
    ];

    $element['line'] = [
      '#type' => 'number',
      '#title' => $this->t('Line'),
      '#description' => $this->t('Line number where the issue was found.'),
      '#default_value' => $item->line ?? NULL,
      '#min' => 0,
    ];

    $element['column'] = [
      '#type' => 'number',
      '#title' => $this->t('Column'),
      '#description' => $this->t('Column number where the issue was found.'),
      '#default_value' => $item->column ?? NULL,
      '#min' => 0,
    ];

    $element['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Human-readable description of the issue.'),
      '#default_value' => $item->description ?? '',
      '#rows' => 3,
    ];

    return $element;
  }

}
