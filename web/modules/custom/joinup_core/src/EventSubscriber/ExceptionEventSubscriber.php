<?php

declare(strict_types=1);

namespace Drupal\joinup_core\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Exception event subscriber.
 */
final readonly class ExceptionEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'logger.channel.joinup_core')]
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Set the priority to -9 to have just a little bit more priority than the
    // RenderArrayNonHtmlSubscriber.
    $events[KernelEvents::VIEW][] = ['onRespond', -9];
    return $events;
  }

  /**
   * Logs a notice in case of a 406 error instead of throwing an exception.
   *
   * @param \Symfony\Component\HttpKernel\Event\ViewEvent $event
   *   The event to process.
   */
  public function onRespond(ViewEvent $event): void {
    $request = $event->getRequest();
    $result = $event->getControllerResult();

    // Overrides \Drupal\Core\EventSubscriber\RenderArrayNonHtmlSubscriber
    // to log a notice instead of throwing an exception.
    // @see \Drupal\Core\EventSubscriber\RenderArrayNonHtmlSubscriber::onRespond()
    if (is_array($result) && $request->getRequestFormat() !== 'html') {
      $format = $request->getRequestFormat();
      \Drupal::logger('joinup_core')->notice('Not acceptable format: ' . $format);

      // If the format is not in the list of formats, use the plain/text format.
      $mimeType = $request->getMimeType($request->getRequestFormat()) ?? 'text/plain; charset=UTF-8';
      $response = new Response('Not acceptable format: ' . $request->getRequestFormat(), 406, [
        'Content-Type' => $mimeType,
      ]);
      $event->setResponse($response);
    }
  }

}
