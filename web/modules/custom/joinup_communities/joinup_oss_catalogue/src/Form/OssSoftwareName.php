<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Let's start with your software name form.
 */
class OssSoftwareName extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oss_software_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter your software’s name'),
      '#placeholder' => $this->t('Enter your software’s name'),
      '#title_display' => 'invisible',
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue to publish it'),
      '#disabled' => TRUE,
    ];
    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
  }

}
