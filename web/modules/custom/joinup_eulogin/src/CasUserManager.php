<?php

declare(strict_types = 1);

namespace Drupal\joinup_eulogin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasProxyHelper;
use Drupal\cas\Service\CasUserManager as OriginalCasUserManager;
use Drupal\externalauth\AuthmapInterface;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Extends 'cas.user_manager' from the cas module.
 */
class CasUserManager extends OriginalCasUserManager {

  use StringTranslationTrait;

  /**
   * Constructs a CasUserManager object.
   *
   * @param \Drupal\externalauth\ExternalAuthInterface $external_auth
   *   The external auth interface.
   * @param \Drupal\externalauth\AuthmapInterface $authmap
   *   The authmap interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $settings
   *   The settings.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param \Drupal\Core\Database\Connection $database_connection
   *   The database connection.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS helper.
   * @param \Drupal\cas\Service\CasProxyHelper $cas_proxy_helper
   *   The CAS Proxy helper.
   * @param \Drupal\Core\Password\PasswordGeneratorInterface $password_generator
   *   The password generator service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ExternalAuthInterface $external_auth, AuthmapInterface $authmap, ConfigFactoryInterface $settings, SessionInterface $session, Connection $database_connection, EventDispatcherInterface $event_dispatcher, CasHelper $cas_helper, CasProxyHelper $cas_proxy_helper, PasswordGeneratorInterface $password_generator, protected MessengerInterface $messenger, protected StateInterface $state) {
    parent::__construct($external_auth, $authmap, $settings, $session, $database_connection, $event_dispatcher, $cas_helper, $cas_proxy_helper, $password_generator);
  }

  /**
   * {@inheritdoc}
   */
  public function register($authname, $local_username, array $property_values = []): UserInterface {
    $user = parent::register($authname, $local_username, $property_values);
    if (
      $this->state->get('joinup_eulogin.accounts_approve', FALSE) &&
      $this->settings->get('user.settings')->get('register') === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL
    ) {
      _user_mail_notify('register_pending_approval', $user);
      $this->messenger->addStatus($this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator. In the meantime, a welcome message with further instructions has been sent to your email address.'));
    }
    return $user;
  }

}
