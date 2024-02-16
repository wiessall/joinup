<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use Composer\InstalledVersions;
use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use EcEuropa\Toolkit\TaskRunner\Commands\ToolCommands;
use Gitonomy\Git\Repository;
use Robo\Collection\CollectionBuilder;

/**
 * Provides commands to build the site's distribution.
 */
class BuildCommands extends AbstractCommands {

  /**
   * Replaces the toolkit:build-dist command.
   *
   * This is a slightly changed fork of original BuildCommands::buildDist(). The
   * main differences are:
   * - Uses the `--worktree-attributes` option of `git archive` to filter out
   *   non-production code.
   * - Uses the `drupal:settings` command to build the `settings.php` file.
   * - The Git tag and commit hash are computed, if not passed.
   *
   * @param array $options
   *   The command line options.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   The Robo collection builder.
   *
   * @hook replace-command toolkit:build-dist
   *
   * @see \EcEuropa\Toolkit\TaskRunner\Commands\BuildCommands::buildDist()
   */
  public function buildDist(array $options): CollectionBuilder {
    $config = $this->getConfig();
    $distDir = $config->get('joinup.dir') . '/' . $options['dist-root'];

    // Delete and (re)create the dist folder.
    $tasks = [$this->taskFilesystemStack()->remove($distDir)->mkdir($distDir)];

    // Copy all (tracked) files to the dist folder.
    $tasks[] = $this->taskExecStack()
      ->stopOnFail()
      ->exec('git archive HEAD --worktree-attributes | tar -x -C ' . $options['dist-root']);

    // Add stage_file_proxy in ephemeral and acceptance environments. This
    // module is required as `require-dev` in the project, as an effect it will
    // not be installed in distribution. Should be removed from `require-dev`
    // and reinstalled as `require` package.
    if (ToolCommands::getDeploymentEnvironment() === 'acc') {
      $version = InstalledVersions::getVersion('drupal/stage_file_proxy');
      $tasks[] = $this->taskComposerRemove()
        ->workingDir($options['dist-root'])
        ->arg('drupal/stage_file_proxy')
        ->dev()
        ->noUpdate()
        ->noScripts()
        ->disablePlugins();
      $tasks[] = $this->taskComposerRequire()
        ->env('COMPOSER_MIRROR_PATH_REPOS', 1)
        ->workingDir($options['dist-root'])
        ->dependency('drupal/stage_file_proxy', $version)
        ->option('update-no-dev')
        ->noScripts();
      // Add stage_file_proxy configuration.
      $tasks[] = $this->taskExec("{$config->get('runner.bin_dir')}/run")
        ->arg('drupal:settings')
        ->arg('acc-override')
        ->option('filename', 'settings.override.php')
        ->option('root', "$distDir/web");
    }

    $tasks[] = $this->taskComposerInstall()
      ->env('COMPOSER_MIRROR_PATH_REPOS', 1)
      ->env('BUILD_DISTRO', 1)
      ->workingDir($options['dist-root'])
      ->optimizeAutoloader()
      ->noDev();

    $tasks[] = $this->taskExec("{$config->get('runner.bin_dir')}/run")
      ->arg('drupal:settings')
      ->arg('prod')
      ->option('root', "{$distDir}/web");

    // Prepare sha and tag variables.
    $tag = $this->getGitTag();
    $sha = $this->getGitCommitHash();

    // Write version tag in manifest.json and VERSION.txt.
    $tasks[] = $this->taskWriteToFile("{$distDir}/manifest.json")->text(
      json_encode([
        'drupal_profile' => 'joinup',
        'project_id' => $config->get('toolkit.project_id'),
        'drupal_version' => ToolCommands::getPackagePropertyFromComposer('drupal/core'),
        'php_version' => phpversion(),
        'toolkit_version' => ToolCommands::getPackagePropertyFromComposer('ec-europa/toolkit'),
        'environment' => ToolCommands::getDeploymentEnvironment(),
        'date' => date('Y-m-d H:i:s'),
        'version' => $tag,
        'sha' => $sha,
      ], JSON_PRETTY_PRINT)
    );
    $tasks[] = $this->taskWriteToFile("{$distDir}/web/VERSION.txt")->text($tag);

    // Collect and execute list of commands set on local runner.yml.
    $commands = $config->get("toolkit.build.dist.commands");
    if (!empty($commands)) {
      $tasks[] = $this->taskExecute($commands);
    }

    return $this->collectionBuilder()->addTaskList($tasks);
  }

  /**
   * Returns the current Git tag.
   *
   * @return string
   *   Current Git tag.
   */
  protected function getGitTag(): string {
    $repository = new Repository($this->getConfig()->get('joinup.dir'));

    // Handle a potential case where the repository has been shallow cloned.
    // Typically, this happens in GitLab pipelines, where the repos are shallow
    // cloned for performance reasons. A shallow clone repository prevents us to
    // get the latest Git tag as no tags are available.
    $is_shallow_repository = trim($repository->run('rev-parse', ['--is-shallow-repository'])) !== 'false';
    if ($is_shallow_repository) {
      $repository->run('fetch', ['--unshallow']);
    }

    return trim($repository->run('describe', ['--tags']));
  }

  /**
   * Returns the current Git commit hash.
   *
   * @return string
   *   Current Git hash.
   */
  protected function getGitCommitHash(): string {
    $repository = new Repository($this->getConfig()->get('joinup.dir'));
    return $repository->getHeadCommit()->getHash();
  }

}
