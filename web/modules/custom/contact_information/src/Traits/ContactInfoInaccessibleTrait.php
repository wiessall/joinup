<?php

declare(strict_types=1);

namespace Drupal\contact_information\Traits;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trait for handling email contact forms if group or contact info is missing.
 */
trait ContactInfoInaccessibleTrait {

  /**
   * The default logger channel to use.
   */
  const string LOGGER_CHANNEL = 'contact_information';

  /**
   * Returns response if contact information cannot receive messages anymore.
   *
   * This method logs a warning message to the given logger channel and triggers
   * an error message being rendered on the current page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   The logger channel to use.
   * @param \Drupal\contact_information\Entity\ContactInformationInterface|null $contact
   *   The contact information entity, if any.
   * @param bool $isAjax
   *   Whether an AJAX response should be built or not.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response command if we have AJAX context, an empty array
   *   otherwise.
   *
   * @see \Drupal\contact_information\EventSubscriber\ExceptionSubscriber::on404()
   */
  public static function logAndBuildContactInfoInaccessibilityResponse(
    Request $request,
    LoggerInterface $loggerChannel,
    ?ContactInformationInterface $contact = NULL,
    bool $isAjax = TRUE,
  ): array|AjaxResponse {
    $loggerMessage = $contact
      ? "Attempting to send message to contact '%contact-id' which does not belong to a group."
      : 'Attempting to send message to a missing contact.';
    $loggerChannel->warning(
      $loggerMessage,
      array_filter([
        '%contact-id' => $contact?->id(),
        '%url' => $request->getRequestUri(),
        '%referer' => $request->headers->get('referer'),
      ])
    );

    $errorMessage = $contact
      ? t('No message can be sent to %contact anymore.', ['%contact' => $contact->getName()])
      : t('No message can be sent to this contact anymore.');

    if ($isAjax) {
      return (new AjaxResponse())
        ->addCommand(new MessageCommand(
          message: $errorMessage,
          options: ['type' => 'error'],
        ));
    }

    \Drupal::messenger()->addError($errorMessage);
    return [];
  }

}
