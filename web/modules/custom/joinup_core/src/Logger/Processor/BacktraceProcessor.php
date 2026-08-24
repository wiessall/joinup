<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Logger\Processor;

use Monolog\LogRecord;

/**
 * Logs the backtrace as a string and drops its unserializable counterparts.
 *
 * The 'backtrace' and 'exception' context entries, added by
 * \Drupal\Core\Utility\Error::decodeException(), carry the full object graph of
 * every stack frame. Serializing them exhausts the memory limit, which turns a
 * trivial PHP warning into a fatal error that is never logged. Only the
 * '@backtrace_string' entry is kept, as an extra, because the message
 * placeholders are stripped from the context before the record is formatted.
 *
 * @see \Drupal\monolog\Logger\Processor\MessagePlaceholderProcessor
 */
class BacktraceProcessor {

  /**
   * {@inheritdoc}
   */
  public function __invoke(LogRecord $record): LogRecord {
    if (!empty($record->context['@backtrace_string'])) {
      $record->extra['backtrace'] = $record->context['@backtrace_string'];
    }

    $context = $record->context;
    unset($context['backtrace'], $context['exception']);

    return $record->with(context: $context);
  }

}
