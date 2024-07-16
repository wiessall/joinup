<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event subscriber listening to events related to joining groups.
 *
 * When an anonymous user wants to join a group, they are redirected to EU Login
 * and a cookie is set to keep track of the group the user wants to join. This
 * event subscriber listens to sign in events, checks for the presence of the
 * cookie, and redirects the user back to the group they wanted to join, so they
 * can decide whether they want to receive the group's notifications.
 */
class JoinGroupSubscriber extends RedirectedEuLoginAuthenticatedSubscriberBase {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The messenger service.
   */
  protected MessengerInterface $messenger;

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time keeping service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(TimeInterface $time, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    parent::__construct($time, $request_stack);
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCookieName(): ?string {
    return 'join_group';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectDestination(CasPostLoginEvent $event): ?string {
    if ($group = $this->getGroup()) {
      // When a user is logged in through EU login and has previously expressed
      // their desire to join a group by setting a cookie, create their
      // membership and redirect them to the group page.
      $uid = (int) $event->getAccount()->id();
      if ($membership = $group->getMembership($uid, OgMembershipInterface::ALL_STATES)) {
        // The user is already subscribed. Delete the cookie and show an
        // appropriate message.
        $this->messenger->addStatus($group->getExistingMembershipMessage($membership));
        $this->deleteCookie();
      }
      elseif ($group instanceof CollectionInterface && $group->hasCodeOfConductToAccept()) {
        $this->deleteCookie();
        return Url::fromRoute(
          'collection.subscribe_to_collection_form',
          ['rdf_entity' => $group->id()],
        )->toString();
      }
      else {
        // Subscribe the user to the group.
        $membership = $group->createMembership((int) $event->getAccount()->id());
        $this->messenger->addStatus($group->getNewMembershipSuccessMessage($membership));
      }
      return parent::getRedirectDestination($event);
    }
    return NULL;
  }

  /**
   * Returns the group that is referenced in the cookie.
   *
   * If the user who is logging in has previously indicated that they want to
   * join a group, a cookie is set which tracks which group they want to join.
   *
   * If a cookie is set and turns out to contain invalid data, the cookie is
   * deleted because it is possibly being tampered with.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The group that is referenced in the cookie, or NULL in case no cookie is
   *   set, or the cookie contains invalid data.
   */
  protected function getGroup(): ?GroupInterface {
    if ($group_id = $this->currentRequest->cookies->get($this->getCookieName())) {
      $group = $this->entityTypeManager->getStorage('rdf_entity')->load($group_id);
      if ($group instanceof GroupInterface) {
        return $group;
      }
      // We are acting on user supplied data in a cookie. If the group cannot be
      // loaded, or an exception occurs, possibly the cookie was tampered with.
      // Do not allow any exceptions to bubble up since they could prevent the
      // user from logging in until they manually clear their cookies. Instead,
      // delete the cookie and carry on as if no cookie was set.
      $this->deleteCookie();
    }
    return NULL;
  }

}
