<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\ConfigProviders;

use Joinup\Traits\DigitQaPipelineAwareTrait;
use OpenEuropa\TaskRunner\Contract\ConfigProviderInterface;
use OpenEuropa\TaskRunner\Traits\ConfigFromFilesTrait;
use Robo\Config\Config;

/**
 * Provides Task Runner configs aware of Digit QA pipeline.
 *
 * This provider should run just after JoinupConfigProvider, which has '0' as
 * priority.
 *
 * @priority -10
 *
 * @see \Joinup\TaskRunner\ConfigProviders\JoinupConfigProvider
 */
class DigitQaPipelineAwareConfigProvider implements ConfigProviderInterface {

  use ConfigFromFilesTrait;
  use DigitQaPipelineAwareTrait;

  /**
   * {@inheritdoc}
   */
  public static function provide(Config $config): void {
    if (static::isDigitQaPipeline()) {
      // Import configurations from ./resources/runner/digit_qa_pipeline/.
      static::importFromFiles($config, glob('resources/runner/digit_qa_pipeline/*.yml'));
      return;
    }

    // In non-DIGIT-QA pipelines we also check for Solr config updates.
    $build_solr_core_config = [
      'task' => 'run',
      'command' => 'solr:build-core-config',
    ];
    foreach (['clean', 'clone'] as $install) {
      $install_commands = $config->get("commands.toolkit:install-$install");
      $install_commands[] = $build_solr_core_config;
      $config->set("commands.toolkit:install-$install", $install_commands);
    }
  }

}
