<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Traits;

use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Trait for check logged messages during a test.
 */
trait LoggerTestTrait {

  use LoggerTrait;


  /**
   * The captured log entries.
   */
  protected array $log = [];

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    // Replace timestamp with the return value of Drupal's
    // TimeInterface::getCurrentTime().
    if (array_key_exists('timestamp', $context)) {
      $context['timestamp'] = \Drupal::time()->getCurrentTime();
    }
    $this->log[$level][] = [
      'message' => $message,
      'context' => $context,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestStack(?RequestStack $requestStack = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentUser(?AccountInterface $current_user = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function setLoggers(array $loggers) {
  }

  /**
   * {@inheritdoc}
   */
  public function addLogger(LoggerInterface $logger, $priority = 0) {
  }

}
