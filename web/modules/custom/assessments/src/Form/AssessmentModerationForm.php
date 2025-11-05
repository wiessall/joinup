<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the assessment moderation form.
 */
final class AssessmentModerationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\assessments\AssessmentInterface $assessment */
    $assessment = $this->entity;

    $form['#title'] = $this->t('Moderate assessment report: @label', [
      '@label' => $assessment->label(),
    ]);

    // Render the assessment entity in view mode (read-only).
    $view_builder = $this->entityTypeManager->getViewBuilder('assessment');
    $form['assessment_display'] = [
      '#type' => 'details',
      '#title' => $this->t('Assessment report'),
      '#open' => TRUE,
      '#weight' => -10,
    ];
    $form['assessment_display']['content'] = $view_builder->view($assessment);

    // Build parent form to get the state field widget.
    $parent_form = parent::buildForm($form, $form_state);

    // Extract only the state field from parent form.
    if (isset($parent_form['state'])) {
      $form['state'] = $parent_form['state'];
      $form['state']['#weight'] = 10;
    }

    // Add the reason field (non-entity field).
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What updates are needed?'),
      '#description' => $this->t('Provide feedback to the submitter about what needs to be updated.'),
      '#states' => [
        'visible' => [
          ':input[name="state[0][value]"]' => ['value' => 'draft'],
        ],
        'required' => [
          ':input[name="state[0][value]"]' => ['value' => 'draft'],
        ],
      ],
      '#weight' => 15,
    ];

    // Add submit button.
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 20,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $state = $form_state->getValue(['state', 0, 'value']);

    // When transitioning to draft (require updates), reason is required.
    if ($state === 'draft') {
      $reason = trim((string) $form_state->getValue('reason', ''));
      if (empty($reason)) {
        $form_state->setErrorByName('reason', $this->t('When requesting updates, you must provide a reason.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\assessments\AssessmentInterface $assessment */
    $assessment = $this->entity;
    $previous_state = $assessment->get('state')->value;
    $new_state = $form_state->getValue(['state', 0, 'value']);

    $result = parent::save($form, $form_state);

    // Determine the action taken and send appropriate notifications.
    if ($new_state === 'published' && $previous_state !== 'published') {
      // Published the report.
      $this->messenger()->addStatus($this->t('The assessment report has been published.'));
      // @todo Send notification to submitter about publication.
    }
    elseif ($new_state === 'draft' && $previous_state !== 'draft') {
      // Requested updates.
      $reason = trim((string) $form_state->getValue('reason', ''));
      $message = $this->t('The submitter has been notified that updates are required.');
      $this->messenger()->addStatus($message);
      // @todo Send notification to submitter with reason.
      // @todo Store reason for notification (could be added as a field).
      unset($reason);
    }
    else {
      $this->messenger()->addStatus($this->t('The assessment report has been updated.'));
    }

    $form_state->setRedirectUrl($assessment->toUrl('collection'));

    return $result;
  }

}
