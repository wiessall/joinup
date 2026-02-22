<?php

declare(strict_types=1);

namespace Drupal\joinup_api\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Returns JSON responses for HTTP exceptions on the assessments API.
 */
final class ApiExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * {@inheritdoc}
   *
   * @return list<string>
   *   An empty array to handle all formats.
   */
  protected function getHandledFormats(): array {
    return [];
  }

  /**
   * Handles all 4xx client error exceptions for the assessments API.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on4xx(ExceptionEvent $event): void {
    $this->handleException($event);
  }

  /**
   * Handles all 5xx server error exceptions for the assessments API.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on5xx(ExceptionEvent $event): void {
    $this->handleException($event);
  }

  /**
   * Handles HTTP exceptions for the assessments API.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  private function handleException(ExceptionEvent $event): void {
    $request = $event->getRequest();

    // Only handle paths under the API endpoint.
    $path = $request->getPathInfo();
    if (!str_starts_with($path, '/api/')) {
      return;
    }

    // If the request requests application/json, return a JSON response.
    // Otherwise, let the default HTML response be returned.
    $acceptable_types = $request->getAcceptableContentTypes();
    if (!is_countable($acceptable_types) || count($acceptable_types) !== 1 || $acceptable_types[0] !== 'application/json') {
      return;
    }

    $exception = $event->getThrowable();
    $response = $exception instanceof HttpExceptionInterface
      ? new JsonResponse(['error' => $exception->getMessage()], $exception->getStatusCode(), $exception->getHeaders())
      : new JsonResponse(['error' => 'An unexpected error occurred.'], 500);

    $event->setResponse($response);
  }

}
