<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\private_message\Plugin\Field\FieldWidget\PrivateMessageThreadMemberWidget;

/**
 * Defines the private message thread member widget.
 *
 * @FieldWidget(
 *   id = "joinup_private_message_member_widget",
 *   label = @Translation("Joinup private message members autocomplete"),
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class PrivateMessageMemberWidget extends PrivateMessageThreadMemberWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = EntityReferenceAutocompleteWidget::formElement($items, $delta, $element, $form, $form_state);

    $recipient = FALSE;
    if ($this->currentUser->hasPermission('access user profiles')) {
      $recipient_id = $this->requestStack->getCurrentRequest()->get('recipient');
      if ($recipient_id) {
        $recipient = $this->entityTypeManager
          ->getStorage('user')
          ->load($recipient_id);
        if ($recipient) {
          $element['target_id']['#default_value'] = $recipient;
        }
      }
    }

    if ($recipient && $this->config->get('hide_recipient_field_when_prefilled')) {
      $element['#element_validate'][] = [__CLASS__, 'validateFormElement'];
      $element['#max_members'] = 1;
    }
    elseif ($max_members = $this->getSetting('max_members')) {
      $element['#element_validate'][] = [__CLASS__, 'validateFormElement'];
      $element['#max_members'] = $max_members;
    }

    $element['target_id']['#selection_handler'] = 'joinup_user_full_name';

    $style_disabled = $this->config->get('remove_css');
    if (!$style_disabled) {
      $element['#attached']['library'][] = 'private_message/members_widget_style';
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state): array {
    $elements = parent::formMultipleElements($items, $form, $form_state);

    if ($this->getSetting('max_members') == 1) {
      // Make the label singular by removing the suffix "(s)".
      $elements['#title'] = rtrim((string) $elements['#title'], '(s)');

      // Remove buttons to add or remove users.
      unset($elements['add_more']);
      unset($elements[0]['_actions']['delete']);
    }

    return $elements;
  }

}
