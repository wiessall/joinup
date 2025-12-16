<?php

declare(strict_types=1);

namespace Drupal\joinup\Listener;

use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use Joinup\TaskRunner\Traits\TaskRunnerTrait;
use Joinup\TestMode;
use Joinup\TestModeManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles setup and teardown tasks for Behat test runs.
 */
class BehatEnvironmentListener implements EventSubscriberInterface {

  use TaskRunnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExerciseCompleted::BEFORE => 'beforeExercise',
      ExerciseCompleted::AFTER => 'afterExercise',
    ];
  }

  /**
   * Setup.
   */
  public function beforeExercise(): void {
    // Setup behat specific settings + set test mode.
    static::runCommand('drupal:settings behat --root=' . static::getPath('web') . ' --sites-subdir=default');
    TestModeManager::set(TestMode::Behat);
  }

  /**
   * Teardown.
   */
  public function afterExercise(): void {
    // Revert to initial state.
    static::runCommand('drupal:settings site-clean --root=' . static::getPath('web') . ' --sites-subdir=default');
    TestModeManager::clear();
  }

}
