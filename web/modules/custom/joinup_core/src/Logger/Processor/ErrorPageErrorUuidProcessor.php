<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Logger\Processor;

use Monolog\LogRecord;

/**
 * Logs the error universal unique ID.
 */
class ErrorPageErrorUuidProcessor {

  /**
   * {@inheritdoc}
   */
  public function __invoke(LogRecord $record): LogRecord {
    if (!empty($record->context['@uuid'])) {
      $record->extra['uuid'] = $record->context['@uuid'];
    }
    return $record;
  }

}
