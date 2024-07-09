<?php

declare(strict_types=1);

namespace Drupal\joinup_log;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for Joinup log entity type.
 */
class JoinupLogViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();
    $base_table = $this->entityType->getBaseTable() ?: $this->entityType->id();

    $data[$base_table]['message'] = [
      'title' => $this->t('Message'),
      'help' => $this->t('The actual message of the log entry.'),
      'field' => [
        // Reuse dblog_message views field plugin.
        'id' => 'dblog_message',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data[$base_table]['variables'] = [
      'title' => $this->t('Variables'),
      'help' => $this->t('The variables of the log entry in a serialized format.'),
      'field' => [
        'id' => 'serialized',
        'click sortable' => FALSE,
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data[$base_table]['severity'] = [
      'title' => $this->t('Severity level'),
      'help' => $this->t('The severity level of the event; ranges from 0 (Emergency) to 7 (Debug).'),
      'field' => [
        'id' => 'standard',
        'options callback' => 'Drupal\Core\Logger\RfcLogLevel::getLevels',
      ],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => 'Drupal\Core\Logger\RfcLogLevel::getLevels',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    return $data;
  }

}
