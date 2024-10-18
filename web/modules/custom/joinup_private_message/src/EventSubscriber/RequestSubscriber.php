<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\private_message_flood\Service\PrivateMessageFloodServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for requests.
 */
class RequestSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected CurrentRouteMatch $routeMatch,
    protected PrivateMessageFloodServiceInterface $floodService,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['newThreadFloodCheck'];
    return $events;
  }

  /**
   * Reacts on request event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Request event.
   */
  public function newThreadFloodCheck(RequestEvent $event): void {
    if ($this->routeMatch->getRouteName() !== 'private_message.private_message_create') {
      return;
    }

    if ($this->floodService->checkUserFlood()) {
      $response = new AjaxResponse();
      $response->addCommand(new MessageCommand($this->t('Sorry, you have reached your limit for posting. Please try again later.'), '.messages__wrapper', ['type' => 'error']));
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

}
