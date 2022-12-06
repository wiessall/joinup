<?php

declare(strict_types = 1);

namespace Drupal\dashboard\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows disabling the site CAS registration.
 */
class DisableRegistrationForm extends FormBase {

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(protected StateInterface $state) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable new accounts registration'),
      '#default_value' => $this->state->get('joinup_eulogin.registration_disabled', FALSE),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('disabled')) {
      $this->state->set('joinup_eulogin.registration_disabled', TRUE);
      $this->messenger()->addWarning($this->t('Registering new users to Joinup is disabled'));
      return;
    }
    $this->state->delete('joinup_eulogin.registration_disabled');
    $this->messenger()->addStatus($this->t('Registering new users to Joinup is enabled'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dashboard_disable_registration';
  }

}
