<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Mail;

use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a NULL mailer backend.
 */
#[Mail(
  id: 'null',
  label: new TranslatableMarkup('Null mailer'),
  description: new TranslatableMarkup("Inhibits the mail delivery."),
)]
class NullMailer implements MailInterface {

  /**
   * {@inheritdoc}
   */
  public function format(array $message): array {
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message): bool {
    return TRUE;
  }

}
