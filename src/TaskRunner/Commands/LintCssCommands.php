<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Commands;

use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use Robo\ResultData;
use Robo\Symfony\ConsoleIO;

/**
 * Command to check coding standard of CSS and S(A|C)SS files.
 */
class LintCssCommands extends AbstractCommands {

  /**
   * Checks the coding standard of CSS and S(A|C)SS files.
   *
   * @command toolkit:lint-css
   *
   * @option fix
   *    Fixes all coding standard violations which can be (and allowed to be)
   *    automatically adjusted.
   *
   * @return int
   *   Status code of the CSS lint task.
   *
   * @throws \Robo\Exception\TaskException
   */
  public function toolkitCodeReview(ConsoleIO $io, array $options = []): int {
    $io->say('Linting CSS...');
    $lintTask = $this->taskExec($this->getNodeBin('stylelint'))
      ->option('config', './.stylelintrc.json')
      ->option('ignore-path', './.gitignore')
      ->arg(implode(DIRECTORY_SEPARATOR, [
        $this->getConfig()->get('joinup.site_dir'),
        '{modules,profiles,themes}',
        '!(contrib)',
        '**',
        '!(*.min).{css,scss,sass}',
      ]))
      ->printMetadata(FALSE);
    if ($isFix = $options['fix']) {
      $lintTask->option('fix');
    }

    if (!$lintTask->run()->getExitCode()) {
      $io->success('CSS linting was successful');
      return ResultData::EXITCODE_OK;
    }

    $io->error(array_filter([
      'CSS linting has failed.',
      $isFix
        ? NULL
        : 'Tip: Run the "toolkit:lint-css --fix" to automatically fix problems of certain rules.',
    ]));
    return ResultData::EXITCODE_ERROR;
  }

}
