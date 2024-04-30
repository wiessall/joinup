<?php

declare(strict_types=1);

namespace Drupal\joinup_test_time\Datetime;

use Drupal\Component\Datetime\Time;
use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides mockable request / current time during testing.
 *
 * @internal
 */
final class TestTime extends Time {

  /**
   * State key of the mocked timestamp, if any.
   *
   * @const string
   */
  const MOCK_TIMESTAMP_STATE_KEY = 'joinup_test_time.timestamp';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new TestTime instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(RequestStack $request_stack, StateInterface $state) {
    parent::__construct($request_stack);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    if ($mock_timestamp = $this->state->get(self::MOCK_TIMESTAMP_STATE_KEY)) {
      return $mock_timestamp;
    }
    return parent::getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime() {
    return (float) $this->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime() {
    if ($mock_timestamp = $this->state->get(self::MOCK_TIMESTAMP_STATE_KEY)) {
      return $mock_timestamp;
    }
    return parent::getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime() {
    return (float) $this->getCurrentTime();
  }

}
