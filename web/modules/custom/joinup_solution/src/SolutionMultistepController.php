<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

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
    if (isset($form['actions']['back_button'])) {
      $form['actions']['back_button']['#weight'] = -15;
    }
    if ($this->stepSettings->group_name !== 'group_confirmation') {
      if (isset($form['actions']['delete'])) {
        $form['actions']['delete']['#access'] = FALSE;
      }
      $form['field_state']['widget']['#options'] = array_intersect_key(
        $form['field_state']['widget']['#options'],
        ['draft' => TRUE, 'draft_after_published' => TRUE]
      );
    }
  }

}
