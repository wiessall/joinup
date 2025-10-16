<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * A collection TCA form.
 */
class CollectionTcaForm extends FormBase {

  /**
   * Returns the bundle of the type the user is trying to create.
   *
   * @return string
   *   The bundle in a human-readable format.
   */
  protected function getEntityBundle(): string {
    return 'collection';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'collection_tca_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['warning'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('In order to create the :bundle you need first check the field below and then press the <em>Yes</em> button to proceed.', [
        ':bundle' => ucfirst($this->getEntityBundle()),
      ]),
    ];

    $form['tca_agreement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and accept <a href=":legal_notice_url">the legal notice</a> and I commit to manage my :bundle on a regular basis.', [
        ':legal_notice_url' => Url::fromRoute('entity.entity_legal_document.canonical', ['entity_legal_document' => 'legal_notice'], ['absolute' => TRUE])->toString(),
        ':bundle' => $this->getEntityBundle(),
      ]),
    ];

    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('No thanks'),
      '#limit_validation_errors' => [],
      '#submit' => ['::cancelSubmit'],
    ];

    $form['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Yes'),
      '#states' => [
        'disabled' => [
          ':input[name="tca_agreement"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('tca_agreement') === 0) {
      $form_state->setError($form['tca_agreement'], "You have to agree that you will manage your {$this->getEntityBundle()} on a regular basis.");
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Submit handler for the 'No thanks' button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function cancelSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect(Url::fromUri('internal:/collections')
      ->getRouteName());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('collection.propose_form');
  }

}
