<?php

declare(strict_types=1);

namespace Drupal\contact_information\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\contact_information\Traits\ContactInfoInaccessibleTrait;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sends error message if visitors try to send a message to a missing contact.
 *
 * We want to prevent core's ExceptionJsonSubscriber to create a JSON response,
 * since it triggers Drupal AJAX JS API to display an error message with the
 * text "Oops, something went wrong...".
 *
 * @see web/core/misc/ajax.js (Drupal.AjaxError)
 * @see \Drupal\Core\EventSubscriber\ExceptionJsonSubscriber::on4xx()
 */
class ExceptionSubscriber extends HttpExceptionSubscriberBase {

  use ContactInfoInaccessibleTrait;
  use StringTranslationTrait;

  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return ['drupal_modal', 'drupal_dialog', 'drupal_ajax'];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    // We want to act before
    // \Drupal\Core\EventSubscriber\ExceptionJsonSubscriber sets a response,
    // because it also stops the propagation of the event to further event
    // listeners.
    return -74;
  }

  /**
   * Handles 404 errors for "email_contact.form" route with AJAX context.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on404(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    $previous = $exception->getPrevious();
    if (
      !$exception instanceof NotFoundHttpException ||
      !$previous instanceof ParamNotConvertedException ||
      $previous->getRouteName() !== 'email_contact.form'
    ) {
      return;
    }

    $response = static::logAndBuildContactInfoInaccessibilityResponse(
      $event->getRequest(),
      $this->loggerFactory->get(static::LOGGER_CHANNEL),
    );
    assert($response instanceof AjaxResponse);
    $event->setResponse($response);
    // We want to display a message; but Drupal AJAX JS also checks whether
    // the response it gets from the server has an "OK" status. If we set this
    // flag on the event, then the response will have the same status what we
    // set it for initially, but otherwise, HttpKernel::handleThrowable() sets
    // 500 status.
    $event->allowCustomResponseCode();
  }

}
