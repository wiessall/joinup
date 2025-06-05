<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

use Drupal\inline_entity_form\ElementSubmit;
use Drupal\simple_multistep\MultistepController;

/**
 * Override the visibility of Multistep form buttons by step.
 */
class SolutionMultistepController extends MultistepController {

  /**
   * {@inheritdoc}
   */
  public function rebuildForm(array &$form): void {
    parent::rebuildForm($form);

    // Move back button in first position (left).
    if (isset($form['actions']['back_button'])) {
      $form['actions']['back_button']['#weight'] = -15;
    }

    // When clicking on next, ensure all IEF widgets are submitted and closed.
    /* @see \Drupal\inline_entity_form\ElementSubmit::attach */
    if (isset($form['actions']['next']['#submit']) && $this->formState->get('inline_entity_form')) {
      \array_unshift($form['actions']['next']['#submit'], [ElementSubmit::class, 'trigger'], 'inline_entity_form_close_all_forms');
      $form['actions']['next']['#ief_submit_trigger_all'] = TRUE;
    }

    // Preserve only specific form actions steps other than confirmation.
    if ($this->stepSettings->group_name !== 'group_confirmation') {
      $form['field_state']['widget']['#options'] = \array_intersect_key($form['field_state']['widget']['#options'], ['delete' => TRUE]);
    }

    if (isset($form['actions']['next'])) {
      $form['actions']['next']['#limit_validation_errors'] = FALSE;
    }
  }

}
