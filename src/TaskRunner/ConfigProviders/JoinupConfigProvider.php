<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\ConfigProviders;

use Drupal\Component\Serialization\Yaml;
use DrupalFinder\DrupalFinder;
use OpenEuropa\TaskRunner\Contract\ConfigProviderInterface;
use OpenEuropa\TaskRunner\Traits\ConfigFromFilesTrait;
use Robo\Config\Config;
use Robo\Exception\AbortTasksException;

/**
 * Runs configs from the ./runner dir between runner.yml.dist and runner.yml.
 */
class JoinupConfigProvider implements ConfigProviderInterface {

  use ConfigFromFilesTrait;

  /**
   * {@inheritdoc}
   */
  public static function provide(Config $config): void {
    // Get the Joinup project full-qualified directory.
    $drupalFinder = new DrupalFinder();

    $startPath = getcwd();
    if (!is_dir("{$startPath}/tests")) {
      // If the top /tests directory doesn't exist, we're probably running
      // inside /dist, when building a new distribution.
      $startPath .= DIRECTORY_SEPARATOR . '..';
    }

    if (!$drupalFinder->locateRoot($startPath)) {
      throw new AbortTasksException('Cannot locate Drupal path.');
    }
    $joinup_dir = $drupalFinder->getComposerRoot();
    $config->set('joinup.dir', $joinup_dir);

    // Create all missed env.* configs from environment variables. The task
    // runner config only resolves the environment variables used as tokens in
    // other configs.
    foreach ($_SERVER as $name => $value) {
      $config->set("env.{$name}", $value);
    }

    // Set the Solr version by reading the .opts.yml file.
    $opts = Yaml::decode(file_get_contents("{$joinup_dir}/.opts.yml"));
    $solr_major_version = $opts['solr_version'] ?? NULL;
    if (!$solr_major_version) {
      throw new \Exception("The 'solr_version' option is missing from .opts.yml file.");
    }
    $config->set('solr.version.major', intval($solr_major_version));

    // Import configurations from ./resources/runner/.
    static::importFromFiles($config, glob('resources/runner/*.yml'));
  }

}
