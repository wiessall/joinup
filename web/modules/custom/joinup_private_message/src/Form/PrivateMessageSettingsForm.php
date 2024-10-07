<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the configuration form for the joinup private message module.
 */
class PrivateMessageSettingsForm extends ConfigFormBase implements ContainerInjectionInterface {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected UserDataInterface $userData,
    protected AccountProxyInterface $currentUser,
    MessengerInterface $messenger,
    protected FormBuilderInterface $formBuilder,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configFactory, $userData);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('user.data'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('form_builder'),
      $container->get('module_handler'),
    );
  }

  /**
   * Foorm id.
   *
   * @return string
   *   returns from id.
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
  public function buildForm(array $form, FormStateInterface $formState): array {
    $private_message_form = [];

    $this->moduleHandler->invoke(
      'private_message',
      'form_user_form_alter',
      [&$private_message_form, $formState,
        'user_form',
      ]);

    // Assuming the element is 'private_message'.
    if (isset($private_message_form['private_messages'])) {

      // Inject the element into your custom form.
      $private_message_form['private_messages']['#title'] = $this->t('Private messages email notifications');
      $form['private_messages'] = $private_message_form['private_messages'];
    }

    return parent::buildForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {

    // Get the user whose account object is being modified.
    $user = $formState->getFormObject()->getEntity();
    // Save the submitted data to the user's UserData.
    $this->userData->set('private_message', $user->id(), 'receive_notification', $formState->getValue([
      'private_messages',
      'receive_notification',
    ]));
    $this->userData->set('private_message', $user->id(), 'notify_when_using', $formState->getValue([
      'private_messages',
      'notify_when_using',
    ]));
    $this->userData->set('private_message', $user->id(), 'number_of_seconds_considered_away', $formState->getValue([
      'private_messages',
      'number_of_seconds_considered_away',
    ]));

    $this->messenger->addMessage($this->t('Private message settings were stored'));

  }

  /**
   * Override getEntity()
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   returns current user.
   */
  public function getEntity(): AccountProxyInterface {
    return $this->currentUser;
  }

  /**
   * Config names.
   *
   * @return string[]
   *   returns config namres
   */
  protected function getEditableConfigNames(): array {
    return [
      'joinup_private_message.settings',
    ];
  }

}
