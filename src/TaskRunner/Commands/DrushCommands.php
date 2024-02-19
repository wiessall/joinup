<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Robo\Collection\CollectionBuilder;
use Robo\Exception\AbortTasksException;

/**
 * Provides commands to enable modules.
 */
class DrushCommands extends AbstractCommands {

  /**
   * Install one or more modules.
   *
   * @param string[] $modules
   *   List of modules to install separated by space.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   The Robo collection builder.
   *
   * @command drush:module-install
   */
  public function installModule(array $modules): CollectionBuilder {
    $config = $this->getConfig();
    $modules_concatenated = implode(',', $modules);
    $task = $this->taskExec("{$config->get('drush.bin')} pm:enable $modules_concatenated --yes");
    return $this->collectionBuilder()->addTask($task);
  }

  /**
   * Validates the drush:module-install command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data object.
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When no module has been passed.
   *
   * @hook validate drush:module-install
   */
  public function validateInstallModule(CommandData $commandData): void {
    if (!$commandData->arguments()['modules']) {
      throw new AbortTasksException('At least one module should be specified as command argument.');
    }
  }

}
