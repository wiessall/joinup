<?php

declare(strict_types=1);

namespace Drupal\contact_information\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\joinup_group\EventSubscriber\RedirectedEuLoginAuthenticatedSubscriberBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Redirects the user back to the group about page.
 */
class ContactInformationRedirectedEuLoginSubscriber extends RedirectedEuLoginAuthenticatedSubscriberBase {

  public function __construct(
    TimeInterface $time,
    RequestStack $request_stack,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($time, $request_stack);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCookieName(): ?string {
    return 'email_contact_info';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectDestination(CasPostLoginEvent $event): ?string {
    if (($contact_information = $this->getContactInformation()) && $contact_information->getRelatedGroup()) {
      return parent::getRedirectDestination($event);
    }
    return NULL;
  }

  /**
   * Returns the contact information entity referenced in the cookie.
   *
   * If the user who is logging in has previously indicated that they want to
   * contact a group, a cookie is set which tracks a contact information entity
   * to act on.
   *
   * If a cookie is set and turns out to contain invalid data, the cookie is
   * deleted because it is possibly being tampered with.
   *
   * @return \Drupal\contact_information\Entity\ContactInformationInterface|null
   *   The contact information entity that is referenced in the cookie, or NULL
   *   in case no cookie is set, or the cookie contains invalid data.
   */
  protected function getContactInformation(): ?ContactInformationInterface {
    if ($contact_information_id = $this->requestStack->getCurrentRequest()?->cookies->get($this->getCookieName())) {
      // @todo This was changed from node to node during the migration of
      //   groups which means that there is no test for it?
      /** @var \Drupal\Core\Entity\ContentEntityInterface $contact_information */
      $contact_information = $this->entityTypeManager->getStorage('node')->load($contact_information_id);
      if ($contact_information instanceof ContactInformationInterface) {
        return $contact_information;
      }
      // We are acting on user supplied data in a cookie. If the contact
      // information entity cannot be loaded, or an exception occurs, possibly
      // the cookie was tampered with. Do not allow any exceptions to bubble up
      // since they could prevent the user from logging in until they manually
      // clear their cookies. Instead, delete the cookie and carry on as if no
      // cookie was set.
      $this->deleteCookie();
    }
    return NULL;
  }

}
