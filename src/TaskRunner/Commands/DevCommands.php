<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use Consolidation\AnnotatedCommand\Attributes as CLI;
use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Robo\Collection\CollectionBuilder;

/**
 * Provides development related commands.
 */
class DevCommands extends AbstractCommands {

  /**
   * Setup git pre-commit/pre-push hook.
   */
  #[CLI\Command(name: 'dev:setup-git-hook')]
  public function setupGitHook(): CollectionBuilder {
    $config = $this->getConfig();
    $joinupDir = $config->get('joinup.dir');
    $binDir = $config->get('runner.bin_dir');
    $runOn = $config->get('dev.git_hook_on');

    // Reset state.
    $collection = [];
    $collection[] = $this->taskExecStack()->exec("rm -f $joinupDir/.git/hooks/pre-push");
    $collection[] = $this->taskExecStack()
      // Temporarily disable the box checker.
      // See https://github.com/phpro/grumphp-shim/issues/30
      // See https://github.com/phpro/grumphp-shim/issues/32
      ->envVars(['BOX_REQUIREMENT_CHECKER' => '0'])
      ->exec("$binDir/grumphp git:deinit --quiet");

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
        // For any other value, disable.
        $this->yell('Static code analysis disabled.', 40, 'yellow');
        break;
    }

    $yaml = <<<YAML
dev:
  # Options: pre-push / pre-commit / disable
  git_hook_on: pre-push
YAML;
    $this->say(sprintf("To change this behavior, specify in your runner.yml:\n<comment>%s</comment>", $yaml));
    return $this->collectionBuilder()->addTaskList($collection);
  }

}
