<?php

declare(strict_types=1);

namespace Drupal\joinup_api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns JSON responses for HTTP errors on the assessments API.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface {

  /**
   * Handles thrown HTTP exceptions for the assessments API.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onKernelRequestException(ExceptionEvent $event): void {
    $request = $event->getRequest();
    $exception = $event->getThrowable();

    if (!$this->appliesToRequest($request->getPathInfo(), $request->getAcceptableContentTypes())) {
      return;
    }

    $response = $exception instanceof HttpExceptionInterface
      ? new JsonResponse(['error' => $exception->getMessage()], $exception->getStatusCode(), $exception->getHeaders())
      : new JsonResponse(['error' => 'An unexpected error occurred.'], 500);

    $event->setResponse($response);
  }

  /**
   * Converts non-JSON error responses to JSON on API paths.
   *
   * Handles cases where the error response is set directly on kernel.request
   * (e.g., maintenance mode) rather than via a thrown exception.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    $statusCode = $response->getStatusCode();
    if ($statusCode < 400) {
      return;
    }

    // Skip if already a JSON response (e.g., set by onKernelRequestException).
    if (str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
      return;
    }

    $request = $event->getRequest();
    if (!$this->appliesToRequest($request->getPathInfo(), $request->getAcceptableContentTypes())) {
      return;
    }

    $errorMessage = Response::$statusTexts[$statusCode] ?? 'An error occurred.';
    $event->setResponse(new JsonResponse(['error' => $errorMessage], $statusCode));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // ExceptionLoggingSubscriber runs with priority 50. No exception handler
    // should run before it as it would prevent it from logging the exception.
    $events[KernelEvents::EXCEPTION][] = ['onKernelRequestException', 49];
    // Handle cases where the error response is set directly on kernel.request
    // (e.g., maintenance mode).
    $events[KernelEvents::RESPONSE][] = ['onKernelResponse'];
    return $events;
  }

  /**
   * Checks if the subscriber should handle this request.
   *
   * @param string $path
   *   The request path.
   * @param array $acceptableTypes
   *   The acceptable content types from the Accept header.
   *
   * @return bool
   *   TRUE if the subscriber should handle the request.
   */
  private function appliesToRequest(string $path, array $acceptableTypes): bool {
    if (!str_starts_with($path, '/api/')) {
      return FALSE;
    }

    return is_countable($acceptableTypes) && count($acceptableTypes) === 1 && $acceptableTypes[0] === 'application/json';
  }

}
