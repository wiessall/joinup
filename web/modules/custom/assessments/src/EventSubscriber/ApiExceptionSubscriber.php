<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Returns JSON responses for 401 exceptions on the assessments API.
 */
final class ApiExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    return 75;
  }

  /**
   * Handles 401 Unauthorized exceptions for the assessments API.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on401(ExceptionEvent $event): void {
    $request = $event->getRequest();

    // Only handle paths under the assessments API.
    if (!str_starts_with($request->getPathInfo(), '/api/assessments/')) {
      return;
    }

    $event->setResponse(new JsonResponse(['error' => $event->getThrowable()->getMessage()], 401));
  }

}
