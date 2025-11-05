<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cas\Event\CasPostLoginEvent;
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

  public function __construct(
    protected readonly TimeInterface $time,
    protected readonly RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Act before other subscribers that intend to set a redirect destination.
      // @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginCasEventsSubscriber::setRedirectAndStatusOnNewAccount()
      CasPostLoginEvent::class => ['onPostLogin', -10],
    ];
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
    try {
      if ($destination = $this->getRedirectDestination($event)) {
        $this->requestStack->getCurrentRequest()?->query->set('destination', $destination);
        // Stop the propagation in order to prevent other downstream subscribers
        // to set a different redirect destination.
        $event->stopPropagation();
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
   * Processes specific logic and, optionally, returns a redirect destination.
   *
   * Note: Implementations should extend this method rather than extending the
   * public self::onPostLogin() method, in order to implement their logic.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The event class instance.
   *
   * @return string|null
   *   The destination where to redirect the user after they were successfully
   *   authenticated with EU Login service. NULL, is such a destination cannot
   *   be determined.
   */
  protected function getRedirectDestination(CasPostLoginEvent $event): ?string {
    // By default, read the destination from the current request.
    return $this->requestStack->getCurrentRequest()?->query->get('destination');
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
