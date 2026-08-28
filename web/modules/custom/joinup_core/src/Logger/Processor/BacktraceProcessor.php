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
 * '@backtrace_string' entry is kept, and only for the records that would
 * otherwise lose it, as the message placeholders are stripped from the context
 * before the record is formatted.
 *
 * @see \Drupal\monolog\Logger\Processor\MessagePlaceholderProcessor
 */
class BacktraceProcessor {

  /**
   * {@inheritdoc}
   */
  public function __invoke(LogRecord $record): LogRecord {
    $trace = $record->context['@backtrace_string'] ?? NULL;
    if (!empty($trace) && !str_contains($record->message, '@backtrace_string')) {
      $record->extra['backtrace'] = $trace;
    }

    $context = $record->context;
    unset($context['backtrace'], $context['exception']);

    return $record->with(context: $context);
  }

}
