<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Service;

use Drupal\private_message\Entity\PrivateMessage;

/**
 * Service for private messages.
 */
class PrivateMessageService {

  /**
   * Clear fields report_text and report_timestamp in reported private message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $privateMessage
   *   Private message.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function flagAsOk(PrivateMessage $privateMessage): void {
    $privateMessage->set('report', '');
    $privateMessage->set('report_timestamp', NULL);
    $privateMessage->save();
  }

}
