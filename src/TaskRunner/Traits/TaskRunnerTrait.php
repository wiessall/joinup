<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Traits;

use Composer\Autoload\ClassLoader;
use DrupalFinder\DrupalFinderComposerRuntime;
use EcEuropa\Toolkit\TaskRunner\Runner;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Reusable methods to allow Task Runner usage from code.
 */
trait TaskRunnerTrait {

  /**
   * Runs an OpenEuropa Task Runner command.
   *
   * @param string $command
   *   The task runner command.
   */
  protected static function runCommand(string $command): void {
    $initialDir = getcwd();
    $input = new StringInput("{$command} --working-dir=" . static::getPath());
    $runner = new Runner(static::getClassLoader(), $input, new NullOutput());
    $runner->run();
    chdir($initialDir);
  }

  /**
   * Returns the class loader.
   *
   * @return \Composer\Autoload\ClassLoader
   *   The class loader.
   */
  protected static function getClassLoader(): ClassLoader {
    return require static::getPath('vendor/autoload.php');
  }

  /**
   * Returns a full-qualified path to a project directory or file.
   *
   * @param string|null $subDir
   *   (optional) A subdirectory relative to the project root. If omitted, the
   *   project root directory is returned.
   *
   * @return string
   *   A path to a project's directory or file.
   */
  protected static function getPath(?string $subDir = NULL): string {
    $drupalFinder = new DrupalFinderComposerRuntime();
    $path = $drupalFinder->getComposerRoot();
    if ($subDir) {
      $path .= DIRECTORY_SEPARATOR . $subDir;
    }
    return $path;
  }

}
