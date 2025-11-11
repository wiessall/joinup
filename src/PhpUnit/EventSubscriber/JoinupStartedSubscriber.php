<?php

declare(strict_types=1);

namespace Joinup\PhpUnit\EventSubscriber;

use Joinup\TaskRunner\Traits\TaskRunnerTrait;
use Joinup\TestMode;
use Joinup\TestModeManager;
use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;

/**
 * Listens to PHPUnit started event.
 */
class JoinupStartedSubscriber implements StartedSubscriber {

  use TaskRunnerTrait;

  /**
   * {@inheritdoc}
   */
  public function notify(Started $event): void {
    static::runCommand("drupal:settings phpunit --root={$this->getPath('web')} --sites-subdir=default");
    TestModeManager::set(TestMode::PhpUnit);

    // @see web/sites/default/settings.php
    define('DO_NOT_REGISTER_ERROR_HANDLER', TRUE);
  }

}
