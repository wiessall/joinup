<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Commands;

use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Robo\ResultData;
use Robo\Symfony\ConsoleIO;
use Symfony\Component\Console\Input\InputOption;

/**
 * Custom, extended code review task for Joinup.
 */
class JoinupCodeReviewCommands extends AbstractCommands {

  protected const TASKMAP = [
    'toolkit:test-phpcs' => [
      'optsVariable' => 'phpcs',
      'resultLabel' => 'PHPcs',
    ],
    'toolkit:opts-review' => [
      'optsVariable' => 'optsReview',
      'resultLabel' => 'Opts review',
    ],
    'toolkit:lint-php' => [
      'optsVariable' => 'lintPhp',
      'resultLabel' => 'Lint PHP',
    ],
    'toolkit:lint-yaml' => [
      'optsVariable' => 'lintYaml',
      'resultLabel' => 'Lint YAML',
    ],
    'toolkit:test-phpstan' => [
      'optsVariable' => 'phpstan',
      'resultLabel' => 'PHPStan',
    ],
    'toolkit:lint-css' => [
      'optsVariable' => 'lintCss',
      'resultLabel' => 'Lint CSS',
    ],
  ];

  /**
   * Command override for Toolkit's code review command.
   *
   * Extends the original command with the option to execute CSS lint.
   *
   * @hook replace-command toolkit:code-review
   *
   * @option lint-css    Execute the command toolkit:lint-css.
   *
   * @see \EcEuropa\Toolkit\TaskRunner\Commands\ToolCommands::toolkitCodeReview()
   */
  public function toolkitCodeReview(ConsoleIO $io, array $options = [
    'lint-css' => InputOption::VALUE_NONE,
  ]): ResultData {
    $options['lint-css'] = !is_bool($options['lint-css']) ? FALSE : $options['lint-css'];
    // LowerCamelize option array keys, then extract.
    $optionsNormalized = array_combine(
      array_map(
        fn ($key) => lcfirst(str_replace('-', '', ucwords($key, '-'))),
        array_keys($options)
      ),
      $options
    );
    extract($optionsNormalized);

    // If at least one option is given, use given options, else use all.
    if (!$phpcs && !$optsReview && !$lintPhp && !$lintYaml && !$phpstan && !$lintCss) {
      $phpcs = $optsReview = $lintPhp = $lintYaml = $phpstan = $lintCss = TRUE;
    }
    $exitCode = 0;
    $results = [];

    foreach (static::TASKMAP as $command => $data) {
      extract($data);

      $results[] = [
        $resultLabel => $this->doRunTask($io, $$optsVariable, $command, $exitCode),
      ];
    }

    $io->title('Results:');
    $io->definitionList(...$results);

    return new ResultData($exitCode);
  }

  /**
   * Conditionally executes the given task, then returns a brief result summary.
   *
   * @param \Robo\Symfony\ConsoleIO $io
   *   The input / output interface.
   * @param bool $needsToBeRun
   *   Whether the given task should be executed or not.
   * @param string $command
   *   The command to run if necessary.
   * @param int $exit
   *   A cummulated exit code. The exit code of the commands are added to this
   *   integer.
   *
   * @return string
   *   A brief, human friendly summary about the task's result. Might be
   *   "failed", "passed" or "skip".
   *
   * @throws \Robo\Exception\TaskException
   */
  protected function doRunTask(ConsoleIO $io, bool $needsToBeRun, string $command, int &$exit): string {
    if ($needsToBeRun) {
      $exit += $code = $this->taskExec($this->getBin('run'))
        ->arg($command)
        ->run()
        ->getExitCode();
      $io->newLine(2);
      return $code > 0 ? 'failed' : 'passed';
    }

    return 'skip';
  }

}
