<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserDataInterface;

/**
 * Private message mail notification settings form.
 */
class PrivateMessageSettingsForm extends FormBase {

  use AutowireTrait;

  /**
   * Constructs a PrivateMessageSettingsForm object.
   *
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   */
  public function __construct(protected UserDataInterface $userData) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_private_message_settings_form';
  }

  /**
   * {@inheritdoc}
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
      $this->userData->set('private_message', $uid, $key, $value);
    }
    $this->messenger()->addStatus($this->t('Private message settings were stored'));
  }

  /**
   * Returns the current user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  public function getEntity(): AccountInterface {
    return $this->currentUser();
  }

}
