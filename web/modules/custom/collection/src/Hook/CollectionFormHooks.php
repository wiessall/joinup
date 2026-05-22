<?php

declare(strict_types=1);

namespace Drupal\collection\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hooks for collection forms.
 */
final class CollectionFormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_alter()
   */
  #[Hook('form_node_collection_edit_form_alter')]
  public function formNodeCollectionEditFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#validate'][] = [$this, 'validateArchive'];
  }

  /**
   * Validates collection archive action.
   */
  public function validateArchive(array &$form, FormStateInterface $form_state): void {
    $collection = $form_state->getFormObject()->getEntity();
    $trigger = $form_state->getTriggeringElement();

    if (($trigger['#state_id'] ?? '') !== 'archived') {
      return;
    }

    if ($collection->getSolutions(TRUE, TRUE)) {
      $form_state->setError(
        $form,
        $this->t('Collection has at least one published solution. Please archive all active solutions first.'),
      );
    }
  }

}
