<?php

declare(strict_types=1);

namespace Drupal\joinup_test_time\Datetime;

use Drupal\Component\Datetime\Time;

/**
 * Provides mockable request / current time during testing.
 *
 * @internal
 */
final class TestTime extends Time {

  /**
   * State key of the mocked timestamp, if any.
   */
  public const string MOCK_TIMESTAMP_STATE_KEY = 'joinup_test_time.timestamp';

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    return $this->getMockTimestamp() ?? parent::getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime(): float {
    return (float) $this->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime() {
    return $this->getMockTimestamp() ?? parent::getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime(): float {
    return (float) $this->getCurrentTime();
  }

  /**
   * Returns the mocked timestamp.
   *
   * @return int|null
   *   The mocked timestamp or NULL if no timestamp was set.
   */
  protected function getMockTimestamp(): ?int {
    // The service is not injected to avoid container circular dependencies.
    // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
    return \Drupal::state()->get(self::MOCK_TIMESTAMP_STATE_KEY);
  }

}
