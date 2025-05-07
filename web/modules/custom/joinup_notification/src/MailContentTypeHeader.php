<?php

declare(strict_types=1);

namespace Drupal\joinup_notification;

/**
 * Provides a class for the mail content type header.
 *
 * This class uses the default headers used by Drupal Core for sending emails.
 * For HTML, delsp is not set as it is not supported by HTML emails (mainly, it
 * simply allows the mail client to wrap the text in a more readable way, but
 * HTML emails are not wrap-able as they are rendered as a whole).
 *
 * @see \Drupal\Core\Mail\MailManager::doMail
 */
enum MailContentTypeHeader: string {

  case TextPlain = 'text/plain; charset=UTF-8; format=flowed; delsp=yes';

  case TextHtml = 'text/html; charset=UTF-8; format=flowed';

}
