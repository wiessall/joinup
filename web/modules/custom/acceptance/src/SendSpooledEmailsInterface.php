<?php

declare(strict_types = 1);

namespace Drupal\joinup_acceptance;

/**
 * Interface for service to consume the emails in spool.
 */
interface SendSpooledEmailsInterface {

  /**
   * Sends out the spooled emails.
   *
   * @param string $transport
   *   The transport method to use. Valid values: "smtp", "sendmail" and "null".
   */
  public function sendSpool(string $transport): void;

}
