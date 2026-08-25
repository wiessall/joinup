<?php

declare(strict_types=1);

namespace Drupal\curated_content_listing\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Hook implementations for curated content listing forms..
 */
final class CuratedContentListingFormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   *
   *  Updates the title of the entity subqueue edit form. In our business
   *  language the subqueues are known as 'Curated content listings'.
   */
  #[Hook('form_entity_subqueue_form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    // Retrieve the original title.
    $label = $form['#title'];

    // Set a generic fallback title.
    $form['#title'] = $this->t('Update curated content listing.');

    // If we have the original subqueue label available,include it in the title.
    if ($label instanceof TranslatableMarkup) {
      $arguments = $label->getArguments();
      if (!empty($arguments['%label'])) {
        $form['#title'] = $this->t('Update curated content listing %label', $arguments);
      }
    }
  }

}
