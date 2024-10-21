<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\private_message\Entity\PrivateMessage;

/**
 * Service for Private Message.
 */
class PrivateMessageService {

  public function __construct(protected Connection $database, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Gets id of private message target.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $privateMessage
   *   Private message param.
   */
  public function getPrivateMessageTarget(PrivateMessage $privateMessage): EntityInterface|null {
    $query = $this->database->select('private_message_thread__private_messages', 'pmtpm')
      ->distinct()
      ->fields('pmtm', ['members_target_id']);
    $query->join('private_message_thread__members', 'pmtm', 'pmtpm.entity_id = pmtm.entity_id');
    $query->join('private_messages', 'pm', 'pmtpm.private_messages_target_id = pm.id');
    $query->where('pmtm.members_target_id != pm.owner')
      ->condition('pm.id', $privateMessage->id());
    $result = $query->execute()->fetchCol();
    if (!empty($result)) {
      $userId = array_shift($result);
      return $this->entityTypeManager->getStorage('user')->load($userId);
    }
    return NULL;
  }

}
