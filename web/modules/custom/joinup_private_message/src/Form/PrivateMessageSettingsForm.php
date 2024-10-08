<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Private message mail notification settings.
 */
class PrivateMessageSettingsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_private_message_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   returns form
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    private_message_form_user_form_alter($form, $form_state);
    $form['action'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => 'Save',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $this->currentUser()->id();
    $userData = \Drupal::service('user.data');
    $settings = [
      'receive_notification' => $form_state->getValue(['private_messages', 'receive_notification']),
      'notify_when_using' => $form_state->getValue(['private_messages', 'notify_when_using']),
      'number_of_seconds_considered_away' => $form_state->getValue(
        [
          'private_messages',
          'number_of_seconds_considered_away',
        ]),
    ];

    foreach ($settings as $key => $value) {
      $userData->set('private_message', $uid, $key, $value);
    }
    $this->messenger()->addStatus($this->t('Private message settings were stored'));
  }

  /**
   * Gets current user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   returns current user.
   */
  public function getEntity(): AccountInterface {
    return $this->currentUser();
  }

  /**
   * Config names.
   *
   * @return string[]
   *   returns config names
   */
  protected function getEditableConfigNames(): array {
    return [
      'joinup_private_message.settings',
    ];
  }

}
