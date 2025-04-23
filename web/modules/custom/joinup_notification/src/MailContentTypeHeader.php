<?php

declare(strict_types=1);

namespace Drupal\joinup_notification;

/**
 * Provides a class for the mail content type header.
 *
 * This class uses the default headers used by Drupal Core for sending emails.
 *
 * @see \Drupal\Core\Mail\MailManager::doMail
 */
enum MailContentTypeHeader: string {

  case TextPlain = 'text/plain; charset=UTF-8; format=flowed; delsp=yes';

  case TextHtml = 'text/html; charset=UTF-8; format=flowed; delsp=yes';

}
