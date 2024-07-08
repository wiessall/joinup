<?php

declare(strict_types=1);

namespace Drupal\joinup_log;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the Joinup log entity schema handler.
 */
class JoinupLogStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entity_type, $reset);

    if ($base_table = $this->storage->getBaseTable()) {
      // Replicate watchdog indexes.
      /* @see dblog_schema */
      $schema[$base_table]['indexes'] += [
        'type' => ['type'],
        'uid' => ['uid'],
        'severity' => ['severity'],
        'group_entity' => ['group_entity'],
      ];
    }

    return $schema;
  }

}
