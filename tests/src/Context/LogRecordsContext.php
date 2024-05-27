<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\ConfigReadOnlyTrait;

/**
 * Behat step definitions to check log records created during tests.
 */
class LogRecordsContext extends RawDrupalContext {

  use ConfigReadOnlyTrait;

  /**
   * Whether Database Log module was originally enabled or not.
   */
  protected bool $dblogStatusOriginal = FALSE;

  /**
   * Timestamp when the current test scenario was started.
   */
  protected int $timeStamp = 0;

  /**
   * Ensures that there are no log entries.
   *
   * @Given the site should not have any log records
   */
  public function assertNoLogRecords(): void {
    $this->failIfLogRecords();
  }

  /**
   * Ensures there are no logs with level lower than the given severity level.
   *
   * @Given the site should not have log records with level lower than :level
   * @Given the site should not have log records with level lower than :level for :type errors
   */
  public function assertNoLogRecordsBelowLevel($level, $type = NULL): void {
    $this->failIfLogRecords($level, $type);
  }

  /**
   * Checks logged messages and throws exception if needed.
   *
   * @param string|null $level
   *   The minimum level of the log records to ignore. Defaults to NULL, which
   *   means log must be completely empty.
   * @param string|null $type
   *   Type of the log records to filter. Defaults to NULL, which
   *   means show all logs.
   *
   * @throws \Exception
   *   If there are any log records with level lower than the given level.
   */
  protected function failIfLogRecords(?string $level = NULL, ?string $type = NULL): void {
    if (!is_null($level)) {
      $levelCapitalized = mb_convert_case($level, MB_CASE_TITLE);
      $levelMapping = array_map(
        fn ($level) => (string) $level,
        RfcLogLevel::getLevels()
      );
      $levelInt = array_search($levelCapitalized, $levelMapping, TRUE);
    }
    else {
      $levelInt = 100;
    }

    $logRecords = array_filter(
      $this->getLogRecords($type),
      function (array $record) use ($levelInt) {
        return $record['severity'] < $levelInt;
      }
    );
    if (!empty($logRecords)) {
      // Keep only the first line of the message.
      array_walk(
        $logRecords,
        function (array &$record) {
          $record['message'] = trim(explode(PHP_EOL, $record['message'])[0]);
        }
      );

      throw new \Exception(sprintf(
        "No log entries are expected, but found %s:\n%s",
        count($logRecords),
        Variable::export($logRecords)
      ));
    }
  }

  /**
   * Asserts that a log record with the given severity and description exists.
   *
   * @param string $severity
   *   The severity of the log record.
   * @param string $description
   *   The description of the log record.
   *
   * @Given there should be an record of severity :severity in the log with description :description
   */
  public function assertLogRecordExists(string $severity, string $description): void {
    $logRecords = $this->getLogRecords(NULL);
    $severity = mb_convert_case($severity, MB_CASE_TITLE);
    \assert(array_search($severity, RfcLogLevel::getLevels()), sprintf(
      "Invalid severity level '%s'. Allowed values are: %s",
      $severity,
      implode(', ', RfcLogLevel::getLevels())
    ));

    $severity = array_search($severity, RfcLogLevel::getLevels());
    $found = FALSE;
    foreach ($logRecords as $record) {
      if ($record['severity'] == $severity && strpos($record['message'], $description) !== FALSE) {
        $found = TRUE;
        break;
      }
    }
    if (!$found) {
      throw new \Exception(sprintf(
        "Log record with severity '%s' and description '%s' not found.",
        $severity,
        $description
      ));
    }
  }

  /**
   * The captured log records since scenario start or since the last reset.
   *
   * @param string|null $type
   *   Type of the log records to filter. Defaults to NULL, which
   *   means show all logs.
   *
   * @return array[]
   *   The captured log records since scenario start or since the last reset.
   */
  protected function getLogRecords($type): array {
    $query = \Drupal::database()->select('watchdog', 'log')
      ->fields('log')
      ->condition('timestamp', $this->timeStamp, '>=');
    if ($type) {
      $query->condition('type', $type);
    }
    return $query->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Ensures that Database Log module is installed.
   *
   * @BeforeScenario @loggedErrors
   */
  public function ensureLogger(): void {
    self::bypassReadOnlyConfig();
    $this->dblogStatusOriginal = \Drupal::moduleHandler()->moduleExists('dblog');
    $this->timeStamp = time();
    \Drupal::service('module_installer')->install(['dblog']);
    if (!$this->dblogStatusOriginal) {
      $this->resetLoggedErrors();
    }
  }

  /**
   * Cleans up after scenario has been finished.
   *
   * If Database Log module was installed by this context, then it will be
   * uninstalled. Otherwise, deletes the log entries recorded during the test.
   *
   * @AfterScenario @loggedErrors
   */
  public function cleanupLoggerIfNeeded(): void {
    if (!$this->dblogStatusOriginal) {
      \Drupal::database()->delete('watchdog')
        ->condition('timestamp', $this->timeStamp, '>=')
        ->execute();
    }
    else {
      \Drupal::service('module_installer')->uninstall(['dblog']);
    }
    self::restoreReadOnlyConfig();
  }

  /**
   * Deletes every preexisting log records since the scenario started.
   *
   * @Given there are no preexisting log records( on the site)
   */
  public function resetLoggedErrors(): void {
    \Drupal::database()->delete('watchdog')
      ->condition('timestamp', $this->timeStamp, '>=')
      ->condition('timestamp', time(), '<=')
      ->execute();
  }

}
