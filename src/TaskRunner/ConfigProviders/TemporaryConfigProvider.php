<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\ConfigProviders;

use OpenEuropa\TaskRunner\Contract\ConfigProviderInterface;
use OpenEuropa\TaskRunner\Traits\ConfigFromFilesTrait;
use Robo\Config\Config;

/**
 * Temporary configurations when transiting from Task Runner to Toolkit runner.
 *
 * Task Runners has been merged in Toolkit. However, the Toolkit runner is
 * oversimplified and not ready to be used by Joinup because of DQA-5962. Until
 * DQA-5962 is fixed we run commands provided in Toolkit with Task Runner. But
 * Toolkit commands are using also default configurations defined under
 * `vendor/ec-europa/toolkit/config/runner`. As a consequence, we need to load
 * also those configurations with a higher priority. This config provider will
 * be removed as soon as DQA-5962 is solved in a way or another.
 *
 * @priority 1600
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/DQA-5962
 *
 * @todo Remove when DQA-5962 is fixed.
 */
class TemporaryConfigProvider implements ConfigProviderInterface {

  use ConfigFromFilesTrait;

  /**
   * {@inheritdoc}
   */
  public static function provide(Config $config): void {
    // Import configurations from `vendor/ec-europa/toolkit/config/runner`.
    static::importFromFiles($config, glob('vendor/ec-europa/toolkit/config/runner/*.yml'));
  }

}
