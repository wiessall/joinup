<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Logger\Formatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Utility\Error;
use Monolog\Formatter\NormalizerFormatter;

/**
 * Telegram handler specific formatter.
 */
class TelegramFormatter extends NormalizerFormatter {

  /**
   * {@inheritdoc}
   */
  public function format(array $record): string {
    $normalized = $this->normalize($record);

    $formatted = [];

    $date = date('Y-m-d H:i', (new DrupalDateTime($normalized['datetime'], 'Europe/Brussels'))->getTimestamp());
    $formatted[] = "[$date] {$normalized['channel']}.{$normalized['level_name']}";
    if (!empty($normalized['extra'])) {
      $formatted[] = 'Context:';
      unset($normalized['extra']['user']);
      foreach ($normalized['extra'] as $key => $value) {
        $formatted[] = "- $key: $value";
      }
    }
    $formatted[] = $this->simplify($normalized['message']);

    $backtrace = array_filter(explode("\n", Error::formatBacktrace($normalized['context']['backtrace'] ?? [])));
    unset($backtrace[0]);

    $formatted[] = 'Backtrace:';
    $formatted[] = implode("\n", array_map(fn(string $line): string => "- $line", $backtrace));
    return implode("\n", $formatted);
  }

  /**
   * Simplifies as string to be shown in log.
   *
   * @param string $string
   *   The initial string.
   *
   * @return string
   *   A simplified version of the string.
   */
  protected function simplify(string $string): string {
    // Remove UUID from message, it already appears in the 'extra' section.
    $string = preg_replace('/\[[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}]\.$/', '', $string);
    // Remove redundant base path as this adds noise.
    $baseDir = realpath(DRUPAL_ROOT . '/..') . '/';
    return str_replace($baseDir, '', $string);
  }

}
