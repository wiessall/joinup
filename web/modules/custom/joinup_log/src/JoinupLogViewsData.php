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

    $data[$base_table]['group']['argument']['id'] = 'og_audience_group';

    return $data;
  }

}
