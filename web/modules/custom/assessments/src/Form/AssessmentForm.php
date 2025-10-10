<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\NestedArray;

/**
 * Form controller for the assessment entity edit forms.
 */
class AssessmentForm extends ContentEntityForm {

  /**
   * Copy only current-step values from form to entity on partial saves.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // If we are in a wizard partial save context, only copy current step fields.
    $is_partial = (bool) $form_state->get('wizard_skip_validation');
    if ($is_partial) {
      $current_step = $this->getCurrentStep($form_state);
      $allowed = $this->getFieldsForStep($current_step);

      // Get submitted values and filter by allowed field machine names.
      $submitted = $form_state->getValues();
      $filtered = [];
      foreach ($allowed as $field_name) {
        if (array_key_exists($field_name, $submitted)) {
          $filtered[$field_name] = $submitted[$field_name];
        }
      }

      // Temporarily replace form_state values with the filtered subset so that
      // parent::copyFormValuesToEntity only maps those values. Avoids clearing
      // unrelated fields and prevents unintended changes.
      $original = $submitted;
      foreach (array_keys($original) as $key) {
        $form_state->unsetValue($key);
      }
      foreach ($filtered as $k => $v) {
        $form_state->setValue($k, $v);
      }

      // Delegate to core for actual mapping of filtered values.
      parent::copyFormValuesToEntity($entity, $form, $form_state);

      // Restore original values to the form state for downstream handlers.
      foreach (array_keys($filtered) as $key) {
        $form_state->unsetValue($key);
      }
      foreach ($original as $k => $v) {
        $form_state->setValue($k, $v);
      }
      return;
    }

    // Default behavior for full submits.
    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  private const STEP_ORGANIZATION = 1;
  private const STEP_REQUIREMENTS = 2;
  private const STEP_STATEMENTS = 3;
  private const STEP_REVIEW = 4;
  private const STEP_COMPLETE = 5;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Default to the General step when no step parameter is present.
    // Note: We avoid using $form_state->setRedirect* here because it only
    // takes effect on submission. Instead, getCurrentStep() will provide a
    // sensible default when the query parameter is missing.
    $current_step = $this->getCurrentStep($form_state) ?? self::STEP_ORGANIZATION;

    // Build the default entity form.
    $form = parent::buildForm($form, $form_state);

    // Add step navigation header.
    $form['steps'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['assessment-steps']],
      '#weight' => -100
    ];
    foreach ($this->getSteps() as $step => $info) {
      $form['steps']["step_$step"] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['assessment-step', $step === $current_step ? 'is-active' : '']],
        'link' => [
          '#type' => 'link',
          '#title' => ($step) . '. ' . $info['label'],
          '#url' => $this->currentStepUrl($step),
        ],
        'desc' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $info['help'],
          '#attributes' => ['class' => ['assessment-step-help']],
        ],
      ];
    }

    // Per-step help below header.
    $form['step_help'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['assessment-step-current-help']],
      'content' => [
        '#markup' => $this->getSteps()[$current_step]['help'],
      ],
      '#weight' => -90,
    ];

    $form['wizard_step'] = [
      '#type' => 'value',
      '#value' => $current_step,
    ];

    // Filter entity fields by step: only keep fields for the current step.
    $allowed_fields = $this->getFieldsForStep($current_step);
    foreach ($form as $key => $element) {
      if ($this->isEntityFieldElement($key, $element) && !in_array($key, $allowed_fields, TRUE)) {
        unset($form[$key]);
      }
    }

    // Step 5: Add a simple summary if needed (no extra fields).
    if ($current_step === self::STEP_REVIEW) {
      $form['review_summary'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Review your submission'),
        'organization' => [
          '#type' => 'item',
          '#title' => $this->t('Public Organization'),
          '#markup' => $this->entity->get('organization')->isEmpty() ? $this->t('Not provided') : $this->entity->get('organization')->view(['label' => 'hidden'])[0]['#markup'] ?? $this->t('Provided'),
        ],
        'requirement' => [
          '#type' => 'item',
          '#title' => $this->t('Binding Requirement'),
          '#markup' => $this->entity->get('requirement')->isEmpty() ? $this->t('Not provided') : $this->t('Provided'),
        ],
        'statements' => [
          '#type' => 'item',
          '#title' => $this->t('Statements'),
          '#markup' => $this->entity->get('statements')->isEmpty() ? $this->t('Not provided') : $this->t('@count statement(s) provided', ['@count' => $this->entity->get('statements')->count()]),
        ],
      ];
    }

    // Step 6: Completion message (read-only page within form).
    elseif ($current_step === self::STEP_COMPLETE) {
      $form['complete'] = [
        '#type' => 'container',
        'title' => [
          '#type' => 'item',
          '#title' => $this->t('Complete'),
        ],
        'message' => [
          '#type' => 'markup',
          '#markup' => $this->t('Your submission has been saved as a draft. You can revisit steps using the navigation above.'),
        ],
        'view_link' => [
          '#type' => 'link',
          '#title' => $this->t('View assessment'),
          '#url' => $this->entity->toUrl('canonical'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ];
    }

    return $form;
  }

  /**
   * Determine if an element key corresponds to a top-level entity field.
   */
  private function isEntityFieldElement(string $key, mixed $element): bool {
    if (str_starts_with($key, '#')) {
      return FALSE;
    }
    // Heuristic: entity field widgets are render arrays with '#type' or '#theme' etc.
    return is_array($element) && array_key_exists('#type', $element) && !in_array($key, ['steps', 'step_help', 'wizard_step', 'container', 'actions'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $current_step = $this->getCurrentStep($form_state);

    // Remove default submit to replace with wizard controls.
    unset($actions['submit']);

    // Back button (not on first step).
    if ($current_step > self::STEP_ORGANIZATION && $current_step <= self::STEP_REVIEW) {
      $actions['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#submit' => ['::submitBack'],
        '#limit_validation_errors' => [],
      ];
    }

    // Continue button (first to penultimate step).
    if ($current_step < self::STEP_REVIEW) {
      $paths = array_map(fn($f) => [$f], $this->getFieldsForStep($current_step));
      array_unshift($paths, ['wizard_step']);
      $actions['continue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Continue'),
        '#submit' => ['::submitContinue'],
        '#limit_validation_errors' => $paths,
      ];
    }

    // Submit for review on last step.
    if ($current_step === self::STEP_REVIEW) {
      $disabled = !$this->isEntityValid();
      $actions['submit_for_review'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit for review'),
        '#submit' => ['::submitForReview'],
        '#disabled' => $disabled,
      ];
    }

    return $actions;
  }

  /**
   * Skip validation on Back/Continue. Keep default on final submission.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Skip validation entirely if partial save explicitly requested.
    if ($form_state->get('wizard_skip_validation')) {
      return;
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Back: save as unpublished and go to previous step.
   */
  public function submitBack(array &$form, FormStateInterface $form_state): void {
    $this->savePartial($form, $form_state);
    $current = $this->getCurrentStep($form_state);
    $this->redirectToStep(max(self::STEP_ORGANIZATION, $current - 1), $form_state);
  }

  /**
   * Continue: save as unpublished and go to next step.
   */
  public function submitContinue(array &$form, FormStateInterface $form_state): void {
    $this->savePartial($form, $form_state);
    $current = $this->getCurrentStep($form_state);
    $this->redirectToStep(min(self::STEP_COMPLETE, $current + 1), $form_state);
  }

  /**
   * Submit for review: for now also save as unpublished then redirect canonical.
   */
  public function submitForReview(array &$form, FormStateInterface $form_state): void {
    // Ensure normal validation applies (button is disabled if invalid anyway).
    $this->savePartial($form, $form_state);
    // Redirect to the completion step of the wizard.
    $this->redirectToStep(self::STEP_COMPLETE, $form_state);
  }

  /**
   * Save current form state into the entity as an unpublished draft.
   */
  private function savePartial(array &$form, FormStateInterface $form_state): void {
    // Do not run entity validation for partial saves.
    $form_state->set('wizard_skip_validation', TRUE);

    // Build entity from submitted values of current step only.
    $this->entity = $this->buildEntity($form, $form_state);

    // Mark unpublished.
    if ($this->entity->hasField('status')) {
      $this->entity->set('status', 0);
    }

    $this->entity->save();
  }

  /**
   * Check if entity passes validation (used to enable/disable final submit).
   */
  private function isEntityValid(): bool {
    $violations = $this->entity->validate();
    return $violations->count() === 0;
  }

  /**
   * Get current step from query or form state.
   */
  private function getCurrentStep(FormStateInterface $form_state): int {
    $step = (int) ($form_state->getValue('wizard_step') ?? (int) ($_GET['step'] ?? self::STEP_ORGANIZATION));
    if ($step < self::STEP_ORGANIZATION || $step > self::STEP_COMPLETE) {
      $step = self::STEP_ORGANIZATION;
    }
    return $step;
  }

  /**
   * Redirect to the given step on the same form.
   */
  private function redirectToStep(int $step, FormStateInterface $form_state): void {
    // Always redirect to the edit route of the current entity.
    $url = Url::fromRoute('entity.assessment.edit_form', [
      'assessment' => $this->entity->id(),
    ], [
      'query' => ['step' => $step],
    ]);

    $form_state->setRedirectUrl($url);
  }

  /**
   * Build a URL to this form with a given step query parameter.
   */
  private function currentStepUrl(int $step): Url {
    $route_name = $this->getRouteMatch()->getRouteName();
    $route_params = $this->getRouteMatch()->getRawParameters()->all();
    $options = ['query' => ['step' => $step]];
    return Url::fromRoute($route_name, $route_params, $options);
  }

  /**
   * Step definitions: label, help, and field keys.
   */
  private function getSteps(): array {
    return [
      self::STEP_ORGANIZATION => [
        'label' => (string) $this->t('Public organization'),
        'help' => (string) $this->t('Select or provide the public organization.'),
        'fields' => ['organization'],
      ],
      self::STEP_REQUIREMENTS => [
        'label' => (string) $this->t('Binding requirements'),
        'help' => (string) $this->t('Add the binding requirements (with participants, affected services, and assets).'),
        'fields' => ['requirement'],
      ],
      self::STEP_STATEMENTS => [
        'label' => (string) $this->t('Statements'),
        'help' => (string) $this->t('Add the statements and their affected services.'),
        'fields' => ['statements'],
      ],
      self::STEP_REVIEW => [
        'label' => (string) $this->t('Submission review'),
        'help' => (string) $this->t('Review your information before submitting for review.'),
        'fields' => [],
      ],
      self::STEP_COMPLETE => [
        'label' => (string) $this->t('Complete'),
        'help' => (string) $this->t('Your assessment process is complete.'),
        'fields' => [],
      ],
    ];
  }

  /**
   * Get field machine names for a step.
   */
  private function getFieldsForStep(int $step): array {
    $steps = $this->getSteps();
    return $steps[$step]['fields'] ?? [];
  }

}
