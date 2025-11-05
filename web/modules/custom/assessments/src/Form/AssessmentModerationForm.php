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
    $form = parent::buildForm($form, $form_state);

    $form['#title'] = $this->t('Moderate assessment report');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $this->messenger()->addStatus($this->t('The assessment has been moderated.'));

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
