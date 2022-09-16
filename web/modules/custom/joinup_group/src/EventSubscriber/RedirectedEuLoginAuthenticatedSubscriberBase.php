<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base event subscriber listening to post login/register events.
 *
 * Accessing some features on the site require users to be authenticated. Some
 * features are sending the user to the EU Login (login or register) process
 * expecting them to come back to the origin page and automatically trigger the
 * original process. An example is the user journey when an anonymous is trying
 * to join a collection.
 *
 * In most of the cases, a cookie is set to keep track of the original action
 * the user wants to perform. The event subscribers extending this abstract, are
 * listening to sign in events, checking for the presence of a specific cookie,
 * and redirecting the user back to the original page where, optionally, a
 * specific action, such as opening a modal, may be automatically triggered.
 */
abstract class RedirectedEuLoginAuthenticatedSubscriberBase implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The time keeping service.
   */
  protected TimeInterface $time;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time keeping service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(TimeInterface $time, RequestStack $request_stack) {
    $this->time = $time;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExternalAuthEvents::LOGIN => 'onLogin',
      // Prevent the success status message and redirect about to be set in
      // JoinupEuLoginCasEventsSubscriber::setRedirectAndStatusOnNewAccount() by
      // acting before and stealing the show.
      // @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginCasEventsSubscriber::setRedirectAndStatusOnNewAccount()
      CasHelper::EVENT_POST_LOGIN => ['onPostLogin', -10],
    ];
  }

  /**
   * Returns the cookie used to keep track of the user journey origin, if any.
   *
   * @return string|null
   *   The cookie name, if any. Most of the implementations need a cookie in
   *   order to perform their logic.
   */
  protected function getCookieName(): ?string {
    return NULL;
  }

  /**
   * Processes the 'on login' logic.
   *
   * Note: Implementations should implement this method rather than extending
   * the public self::onLoad() method, in order to implement their logic.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthLoginEvent $event
   *   The event class instance.
   *
   * @return \Drupal\Core\Url|null
   *   The URL where to redirect the user after they were successfully
   *   authenticated with EU Login service. NULL, is such a URL cannot be
   *   determined.
   */
  abstract protected function getRedirectUrl(ExternalAuthLoginEvent $event): ?Url;

  /**
   * Checks whether we've landed here as result of routing to EU Login.
   *
   * Implementations, usually, are checking for the existence of a certain
   * cookie and foe the cookie validity.
   *
   * @return bool
   *   Whether we've landed here as result of routing to EU Login.
   */
  abstract protected function isRedirectedEuLoginAuthenticated(): bool;

  /**
   * Listens to the ExternalAuthEvents::LOGIN event.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthLoginEvent $event
   *   The event firing when a user logs in through EU login.
   */
  public function onLogin(ExternalAuthLoginEvent $event): void {
    try {
      if ($url = $this->getRedirectUrl($event)) {
        $this->requestStack->getCurrentRequest()->query->set('destination', $url->toString());
      }
    }
    catch (\Throwable $exception) {
      // We are acting on user supplied data in a cookie. Do not allow any
      // exception to bubble up since this could prevent the user from logging
      // in until they manually clear their cookies. Instead, delete the cookie
      // and proceed as if no cookie was set.
      $this->deleteCookie();
    }
  }

  /**
   * Listens to the CasHelper::EVENT_POST_LOGIN event.
   *
   * If a valid cookie is set indicating that the user is logging in with the
   * intention of returning to the origin page, prevent the user from being
   * redirected elsewhere.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The event firing after successful CAS login.
   */
  public function onPostLogin(CasPostLoginEvent $event): void {
    if ($this->isRedirectedEuLoginAuthenticated()) {
      // Ensure our redirection takes precedence over any other listeners. The
      // POST_LINK event fires after the LOGIN event and would redirect the user
      // to their profile page. This would overwrite our redirection.
      // @see ::onLogin()
      // @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginCasEventsSubscriber::setRedirectAndStatusOnNewAccount()
      $event->stopPropagation();
    }
  }

  /**
   * Deletes the cookie which tracks the origin of the user journey.
   */
  protected function deleteCookie(): void {
    if ($cookie_name = $this->getCookieName()) {
      setcookie($cookie_name, '', $this->time->getRequestTime() - 86400, '/');
    }
  }

}
