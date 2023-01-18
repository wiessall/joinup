<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use OpenEuropa\TaskRunner\Commands\AbstractCommands;
use Robo\Collection\CollectionBuilder;
use Robo\Exception\AbortTasksException;

/**
 * Provides commands to enable/disable modules.
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
   * Uninstall one or more modules.
   *
   * @param string[] $modules
   *   List of modules to install separated by space.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   The Robo collection builder.
   *
   * @command drush:module-uninstall
   */
  public function uninstallModule(array $modules): CollectionBuilder {
    $config = $this->getConfig();
    $modules_concatenated = implode(',', $modules);
    $task = $this->taskExec("{$config->get('drush.bin')} pm:uninstall $modules_concatenated --yes");
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

  /**
   * Validates the drush:module-uninstall command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data object.
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When no module has been passed.
   *
   * @hook validate drush:module-uninstall
   */
  public function validateUninstallModule(CommandData $commandData): void {
    if (!$commandData->arguments()['modules']) {
      throw new AbortTasksException('At least one module should be specified as command argument.');
    }
  }

}
