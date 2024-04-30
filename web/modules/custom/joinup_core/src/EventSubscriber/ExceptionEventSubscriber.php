<?php

declare(strict_types=1);

namespace Drupal\joinup_core\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\sparql_entity_storage\Encoder\SparqlEncoder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Exception event subscriber.
 */
final class ExceptionEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an ExceptionEventSubscriber object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(protected LoggerChannelInterface $logger) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Set the priority to -9 to have just a little bit more priority than the
    // RenderArrayNonHtmlSubscriber.
    $events[KernelEvents::VIEW][] = ['onRespond', -9];
    // Set the priority to -69 to have just a little bit more priority than the
    // DefaultExceptionSubscriber.
    $events[KernelEvents::EXCEPTION][] = ['on4xx', -69];
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

  /**
   * Handles all 4xx errors for all serialization failures.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on4xx(ExceptionEvent $event): void {
    $throwable = $event->getThrowable();
    if (!$throwable instanceof NotFoundHttpException) {
      return;
    }

    if ($event->getThrowable()->getStatusCode() !== 404) {
      return;
    }

    $request = $event->getRequest();
    $format = $request->getRequestFormat();
    if (in_array($format, array_keys(SparqlEncoder::getSupportedFormats()))) {
      // This ensures that there is a format for the response and avoid cases
      // where debug information is logged about deprecations related to passing
      // null arguments to internal PHP functions.
      // @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter::onResponse()
      $request->setRequestFormat('txt');
    }
  }

}
