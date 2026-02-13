<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
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
    if ($this->stepSettings->group_name !== 'group_confirmation' && isset($form['field_state']['widget']['#options'])) {
      $form['field_state']['widget']['#options'] = \array_intersect_key($form['field_state']['widget']['#options'], ['delete' => TRUE]);
    }

    // Preserve all form state values between steps and mute validation errors
    // when going back.
    if (isset($form['actions']['back_button'])) {
      $form['actions']['back_button']['#submit'] = [[static::class, 'submitBackButton']];
      $form['actions']['back_button']['#name'] = 'simple_multistep_back_button';
      $form['actions']['back_button']['#limit_validation_errors'] = FALSE;
      $form['#validate'][] = [static::class, 'validateBackButtonForm'];
    }
    if (isset($form['actions']['next'])) {
      $form['actions']['next']['#limit_validation_errors'] = FALSE;
    }

  }

  /**
   * Submit handler for back_button.
   */
  public static function submitBackButton(array &$form, FormStateInterface $form_state): void {
    // Rebuild the entity in form state to preserve values (same as next).
    /* @see simple_multistep_register_next_step */
    $entity_form = $form_state->getFormObject();
    if ($entity_form instanceof EntityFormInterface) {
      $entity_updated = $entity_form->buildEntity($form, $form_state);
      $entity_form->setEntity($entity_updated);
    }

    // Call original submit.
    simple_multistep_register_back($form, $form_state);
  }

  /**
   * Validate handler to clear errors with back button.
   */
  public static function validateBackButtonForm(array $form, FormStateInterface $form_state): void {
    if ($form_state->getTriggeringElement()['#name'] === 'simple_multistep_back_button' && $form_state->getErrors()) {
      $form_state->clearErrors();
    }
  }

}
