<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Robo\Collection\CollectionBuilder;
use Robo\Exception\AbortTasksException;

/**
 * Provides commands to enable modules.
 */
class DrushCommands extends AbstractCommands {

  /**
   * Install one or more modules.
   */
  #[CLI\Argument(name: 'modules', description: 'List of modules to install separated by space.')]
  #[CLI\Command(name: 'drush:module-install')]
  public function installModule(array $modules): CollectionBuilder {
    $config = $this->getConfig();
    $modules_concatenated = implode(',', $modules);
    $task = $this->taskExec("{$config->get('drush.bin')} pm:enable $modules_concatenated --yes");
    return $this->collectionBuilder()->addTask($task);
  }

  /**
   * Validates the drush:module-install command.
   */
  #[CLI\Argument(name: 'commandData', description: 'The command data object')]
  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'drush:module-install')]
  public function validateInstallModule(CommandData $commandData): void {
    if (!$commandData->arguments()['modules']) {
      throw new AbortTasksException('At least one module should be specified as command argument.');
    }
  }

}
