<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Robo\Collection\CollectionBuilder;

/**
 * Provides development related commands.
 */
class DevCommands extends AbstractCommands {

  /**
   * Setup git pre-commit/pre-push hook.
   *
   * Specify dev.git_hook_on: pre-push / pre-commit or false to disable.
   * Defaults to pre-push.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   The Robo collection builder.
   *
   * @command dev:setup-git-hook
   */
  public function setupGitHook(): CollectionBuilder {
    $config = $this->getConfig();
    $joinupDir = $config->get('joinup.dir');
    $binDir = $config->get('runner.bin_dir');
    $runOn = $config->get('dev.git_hook_on');

    // Reset state.
    $collection = [];
    $collection[] = $this->taskExecStack()->exec("rm -f $joinupDir/.git/hooks/pre-push");
    $collection[] = $this->taskExecStack()->exec("$binDir/grumphp git:deinit --quiet");

    // Activate.
    switch ($runOn) {
      case 'pre-push':
        $collection[] = $this->taskFilesystemStack()->symlink('../../scripts/git/pre_push', "$joinupDir/.git/hooks/pre-push");
        $this->yell('Static code analysis enabled (git pre-push).');
        break;

      case 'pre-commit':
        $collection[] = $this->taskExecStack()->exec("$binDir/grumphp git:init --quiet");
        $this->yell('Static code analysis enabled (git pre-commit).');
        break;

      default:
        $this->yell('Static code analysis disabled.', 40, 'yellow');
        $this->say('Specify <comment>dev.git_hook_on: pre-push / pre-commit</comment> in your runner.yml to enable.');
        break;
    }

    return $this->collectionBuilder()->addTaskList($collection);
  }

}
