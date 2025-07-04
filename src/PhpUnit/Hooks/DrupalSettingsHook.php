<?php

declare(strict_types=1);

namespace Joinup\PhpUnit\Hooks;

use Joinup\TaskRunner\Traits\TaskRunnerTrait;
use Joinup\TestMode;
use Joinup\TestModeManager;
use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;

/**
 * Hooks into PHPUnit test suite in order to provide proper Drupal settings.
 */
class DrupalSettingsHook implements BeforeFirstTestHook, AfterLastTestHook {

  use TaskRunnerTrait;

  /**
   * {@inheritdoc}
   */
  public function executeBeforeFirstTest(): void {
    static::runCommand("drupal:settings phpunit --root={$this->getPath('web')} --sites-subdir=default");
    TestModeManager::set(TestMode::PhpUnit);
  }

  /**
   * {@inheritdoc}
   */
  public function executeAfterLastTest(): void {
    static::runCommand("drupal:settings site-clean --root={$this->getPath('web')} --sites-subdir=default");
    TestModeManager::clear();
  }

}
