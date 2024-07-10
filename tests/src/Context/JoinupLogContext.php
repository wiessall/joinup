<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Behat step definitions related to joinup_log entity.
 */
class JoinupLogContext extends RawDrupalContext {

  /**
   * Timestamp when the current test scenario was started.
   */
  protected int $timestamp = 0;

  /**
   * Prepare clean up data.
   *
   * @BeforeScenario
   */
  public function prepareData(): void {
    $this->timestamp = time();
  }

  /**
   * Checks if joinup_log entry exists.
   *
   * @param string $message
   *   Message.
   *
   * @Then Log entry with the message :message should exist.
   */
  public function joinupLogEntryShouldExist(string $message): void {
    $count = \Drupal::entityQuery('joinup_log')
      ->accessCheck(FALSE)
      ->condition('timestamp', $this->timestamp, '>=')
      ->condition('message', $message)
      ->count()
      ->execute();

    if ($count === 0) {
      throw new \RuntimeException("Log with message '$message' was not found.");
    }
  }

}
