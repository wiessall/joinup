<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\og\Entity\OgMembership;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Alters the redirect to 'joinup_group.transfer_group_ownership_confirm' route.
 *
 * In case the action has registered errors, we cancel the redirection to the
 * confirmation form. Instead, we redirect back to the main view and we show
 * some warnings, explaining the errors.
 */
class TransferGroupOwnershipSubscriber implements EventSubscriberInterface {

  /**
   * The user private temp store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * Constructs a new event subscriber.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The user private temp store factory.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected MessengerInterface $messenger,
    PrivateTempStoreFactory $tempStoreFactory,
  ) {
    $this->tempStore = $tempStoreFactory->get('joinup_transfer_group_ownership');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['alterRedirection']];
  }

  /**
   * Alters the redirection to 'joinup_group.transfer_group_ownership_confirm'.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   If the temp store couldn't acquire the lock.
   */
  public function alterRedirection(ResponseEvent $event): void {
    $response = $event->getResponse();
    if (!$response->isRedirect() || !$response instanceof RedirectResponse) {
      return;
    }

    $target_url = $this->getTargetUrl($response);
    if (!$target_url || !$target_url->isRouted() || $target_url->getRouteName() !== 'joinup_group.transfer_group_ownership_confirm') {
      return;
    }

    $data = $this->tempStore->get($this->currentUser->id());
    // If warning or error messages were recorded, we alter the redirect to
    // reload the view but we want to show the messages to the user.
    if (!empty($data['messages'])) {
      foreach ($data['messages'] as $severity => $messages) {
        foreach ($messages as $message) {
          $this->messenger->addMessage($message, $severity);
        }
      }
      $membership = OgMembership::load($data['membership']);
      $entity_type_id = $membership->getGroup()->getEntityTypeId();
      $view_url = Url::fromRoute("entity.$entity_type_id.member_overview", [
        $entity_type_id => $membership->getGroupId(),
      ]);
      $response->setTargetUrl($view_url->toString());
      // We go back to the view, expecting a new user selection, so we clear
      // the old user input.
      $this->tempStore->delete($this->currentUser->id());
    }
  }

  /**
   * Gets URL from response's target.
   *
   * @param \Symfony\Component\HttpFoundation\RedirectResponse $response
   *   Response.
   *
   * @return \Drupal\Core\Url|null
   *   The URL where the user is redirected.
   */
  protected function getTargetUrl(RedirectResponse $response): ?Url {
    $path = parse_url($response->getTargetUrl(), PHP_URL_PATH);
    if (!$path) {
      return NULL;
    }

    $path = rtrim($path, '/');
    $path = substr($path, strlen(base_path()));

    if (UrlHelper::isExternal($path)) {
      return NULL;
    }

    try {
      return Url::fromUri("internal:/$path");
    }
    catch (\InvalidArgumentException $e) {
      return NULL;
    }
  }

}
