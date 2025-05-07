<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin class of the 'joinup_group_reference_with_timestamp' widget.
 */
#[FieldWidget(
  id: 'joinup_group_reference_with_timestamp',
  label: new TranslatableMarkup('Reference with timestamp'),
  field_types: ['joinup_group_reference_with_timestamp'],
)]
class ReferenceWithTimestampWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ): array {
    $elements = parent::formElement($items, $delta, $element, $form, $form_state);

    $elements['timestamp'] = [
      '#title' => $this->t('Timestamp'),
      '#type' => 'number',
      '#default_value' => $items[$delta]->timestamp ?? NULL,
      '#delta' => $delta,
    ];
    return $elements;
  }

}
