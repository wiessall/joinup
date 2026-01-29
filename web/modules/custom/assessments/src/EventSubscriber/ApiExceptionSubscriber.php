<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns JSON responses for all exceptions on the assessments API.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Priority 75 runs before Drupal's default exception handlers.
    return [
      KernelEvents::EXCEPTION => ['onException', 75],
    ];
  }

  /**
   * Handles all exceptions for the assessments API.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event): void {
    $request = $event->getRequest();

    // Only handle paths under the assessments API.
    if (!str_starts_with($request->getPathInfo(), '/api/assessments/')) {
      return;
    }

    // Check if client expects JSON via Accept header or request format.
    if (!$this->isJsonRequest($request)) {
      return;
    }

    $exception = $event->getThrowable();
    $statusCode = $exception instanceof HttpExceptionInterface
      ? $exception->getStatusCode()
      : 500;

    $message = match ($statusCode) {
      400 => 'Bad request',
      401 => 'Unauthorized',
      403 => 'Forbidden',
      404 => 'Not found',
      405 => 'Method not allowed',
      409 => 'Conflict',
      422 => 'Unprocessable entity',
      429 => 'Too many requests',
      default => $statusCode >= 500 ? 'Server error' : 'Error',
    };

    $event->setResponse(new JsonResponse(['error' => $message], $statusCode));
  }

  /**
   * Determines if the request expects a JSON response.
   *
   * Checks both the request format (if set) and the Accept header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if the request expects JSON.
   */
  protected function isJsonRequest(Request $request): bool {
    $format = $request->getRequestFormat(NULL);
    if ($format === 'json') {
      return TRUE;
    }

    $acceptableTypes = $request->getAcceptableContentTypes();
    foreach ($acceptableTypes as $type) {
      if (str_contains($type, 'json')) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
