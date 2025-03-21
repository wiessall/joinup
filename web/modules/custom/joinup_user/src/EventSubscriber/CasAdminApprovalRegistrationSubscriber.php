<?php

declare(strict_types=1);

namespace Drupal\joinup_user\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthRegisterEvent;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Acts when the new account creation is subject of admin approval.
 *
 * This subscriber provides tha standard Drupal emails and status message when
 * a new account is registered but the site requires admin approval.
 *
 * @see \Drupal\cas\Subscriber\CasAdminApprovalRegistrationSubscriber.
 */
class CasAdminApprovalRegistrationSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ExternalAuthEvents::REGISTER => ['onBlockedAccountRegistration', -10]];
  }

  /**
   * Acts just after a new blocked account is registered and provides a message.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthRegisterEvent $event
   *   The ExternalAuth register event.
   */
  public function onBlockedAccountRegistration(ExternalAuthRegisterEvent $event): void {
    if ($event->getProvider() !== 'cas') {
      return;
    }

    $account = $event->getAccount();
    if ($account->isBlocked()) {
      if ($this->configFactory->get('cas.settings')->get('user_accounts.auto_register_follow_registration_policy')) {
        $user_settings = $this->configFactory->get('user.settings');
        if ($user_settings->get('register') === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL) {
          // Provide the standard Drupal emails.
          _user_mail_notify('register_pending_approval', $account);
          // Provide the standard Drupal status message.
          $contact_form_url = Url::fromRoute('contact_form.contact_page')->toString();
          $message = $this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator. In the meantime, a welcome message has been sent to your email address. We would like to inform you that our system automatically places accounts registered with certain generic email domains under review. This measure helps us maintain the integrity of the platform by minimising the risk of spam or misuse. If your account has not been validated in the following 3 business days, please contact us through our <a href="@contact_form_url">contact form</a>.', [
            '@contact_form_url' => $contact_form_url,
          ]);
          $this->messenger->addStatus($message);
        }
      }
    }
    $event->stopPropagation();
  }

}
