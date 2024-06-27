<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use Consolidation\AnnotatedCommand\Attributes as CLI;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Joinup\Traits\DigitQaPipelineAwareTrait;
use Robo\Result;

/**
 * Extra tool commands/hooks for toolkit.
 */
class ToolCommands extends AbstractCommands {

  use DigitQaPipelineAwareTrait;

  /**
   * Extra tasks to run after toolkit:opts-review.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'toolkit:opts-review')]
  public function postOptsReview($result): Result|int|null {
    // If we are on Gitlab, run some extra commands...
    if (self::isDigitQaPipeline()) {
      $result = $this->taskExec($this->getBin('run'))->arg('toolkit:post-qa-code-review')->run();
      if (!$result->wasSuccessful()) {
        return $result;
      }
    }
    return $result;
  }

}
