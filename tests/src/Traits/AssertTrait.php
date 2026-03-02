<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Exception\ExpectationException;
use Countable;

/**
 * Provides assertion helpers for Behat contexts and traits.
 */
trait AssertTrait {

  /**
   * Asserts a condition, throwing an ExpectationException on failure.
   *
   * @param bool $condition
   *   The condition to assert.
   * @param string $message
   *   The failure message shown in the Behat report.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when the condition is FALSE.
   */
  protected function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }
  }

  /**
   * Asserts that two values are identical, showing a serialized diff on failure.
   *
   * @param mixed $expected
   *   The expected value.
   * @param mixed $actual
   *   The actual value.
   * @param string $message
   *   Optional context message prepended to the diff.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when expected and actual are not identical.
   */
  protected function assertSameValues(mixed $expected, mixed $actual, string $message = ''): void {
    if ($actual === $expected) {
      return;
    }
    $detail = sprintf(
      "Expected: %s\nActual:   %s",
      var_export($expected, TRUE),
      var_export($actual, TRUE),
    );
    throw new ExpectationException(
      $message ? "$message\n$detail" : $detail,
      $this->getSession()->getDriver(),
    );
  }

  /**
   * Asserts that a countable has the expected number of items.
   *
   * @param int $expected
   *   The expected count.
   * @param \Countable|array $actual
   *   The value to count.
   * @param string $message
   *   Optional failure message. Auto-generated when empty.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when the count does not match.
   */
  protected function assertCount(int $expected, Countable|array $actual, string $message = ''): void {
    $count = count($actual);
    $this->assertCondition(
      $count === $expected,
      $message ?: sprintf('Expected %d item(s) but got %d.', $expected, $count),
    );
  }

}
