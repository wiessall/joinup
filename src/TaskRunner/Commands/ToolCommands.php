<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Joinup\Traits\DigitQaPipelineAwareTrait;

/**
 * Extra tool commands/hooks for toolkit.
 */
class ToolCommands extends AbstractCommands {

  use DigitQaPipelineAwareTrait;

  /**
   * Extra tasks to run after toolkit:opts-review.
   *
   * @hook post-command toolkit:opts-review
   */
  public function postOptsReview($result, CommandData $commandData) {
    // If we are on Gitlab, run some extra commands...
    if (self::isDigitQaPipeline()) {
      $result = $this->taskExec($this->getBin('run'))->arg('testing:post-qa-code-review')->run();
      if (!$result->wasSuccessful()) {
        return $result;
      }
    }
  }

}
