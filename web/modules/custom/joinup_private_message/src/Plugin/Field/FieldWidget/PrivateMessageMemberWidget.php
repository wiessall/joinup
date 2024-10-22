<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'joinup_private_message_member_widget' widget.
 */
#[FieldWidget(
  id: 'joinup_private_message_member_widget',
  label: new TranslatableMarkup('Joinup private message members autocomplete'),
  description: new TranslatableMarkup('Defines the private message thread member widget.'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
class PrivateMessageMemberWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached']['library'][] = 'joinup_private_message/new_message_member_cardinality_one';
    $element['target_id']['#selection_handler'] = 'joinup_user_full_name';
    $element['#element_validate'][] = [__CLASS__, 'validateFormElement'];
    $element['target_id']['#title'] = $this->t('To:');
    unset($element['target_id']['#description']);

    return $element;
  }

  /**
   * Validates the form.
   */
  public static function validateFormElement(array $element, FormStateInterface $form_state): void {
    if (count($form_state->getValue('members')) > 1) {
      $form_state->setError($element, t('Multiple members is not allowed.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    // Correctly massage form values for the fake single element.
    return [$values['target_id']];
  }

}
