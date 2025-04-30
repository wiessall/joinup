<?php

declare(strict_types=1);

namespace Drupal\joinup_test_time\Traits;

use Drupal\joinup_test_time\Datetime\TestTime;

/**
 * Trait for manipulating time during functional tests.
 */
trait TestTimeTrait {

  /**
   * Status of the originally mocked time before testing began.
   *
   * @var int|null
   */
  protected ?int $originalTime;

  /**
   * Sets the timestamp to mock current time.
   *
   * @param int|null $timestamp
   *   The timestamp to mock, or NULL to disarm time mocking.
   */
  public function setMockedTime(?int $timestamp): void {
    static $backupWasDone;
    if (!isset($backupWasDone)) {
      $this->originalTime = \Drupal::state()->get(TestTime::MOCK_TIMESTAMP_STATE_KEY);
      $backupWasDone = TRUE;
    }

    if (is_null($timestamp)) {
      \Drupal::state()->delete(TestTime::MOCK_TIMESTAMP_STATE_KEY);
      return;
    }
    \Drupal::state()->set(TestTime::MOCK_TIMESTAMP_STATE_KEY, $timestamp);
  }

  /**
   * Restores the originally mocked time.
   */
  public function restoreOriginalTime(): void {
    if (isset($this->originalTime)) {
      \Drupal::state()->set(TestTime::MOCK_TIMESTAMP_STATE_KEY, $this->originalTime);
      return;
    }
    \Drupal::state()->delete(TestTime::MOCK_TIMESTAMP_STATE_KEY);
  }

}
