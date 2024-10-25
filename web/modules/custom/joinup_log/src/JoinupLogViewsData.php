<?php

declare(strict_types=1);

namespace Drupal\joinup_log;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\joinup_log\Entity\JoinupLog;
use Drupal\views\EntityViewsData;

/**
 * Provides the views data for Portal log entity type.
 */
class JoinupLogViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();
    $base_table = $this->entityType->getBaseTable() ?: $this->entityType->id();

    $data[$base_table]['severity']['filter'] = [
      'id' => 'in_operator',
      'options callback' => RfcLogLevel::class . '::getLevels',
    ];

    $data[$base_table]['type']['filter'] = [
      'id' => 'in_operator',
      'options callback' => JoinupLog::class . '::allowedTypes',
    ];

    $data[$base_table]['variables']['field']['id'] = 'log_unserializer';
    $data[$base_table]['group']['argument']['id'] = 'og_audience_group';

    return $data;
  }

}
