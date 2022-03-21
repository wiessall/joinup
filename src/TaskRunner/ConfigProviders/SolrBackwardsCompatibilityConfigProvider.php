<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\ConfigProviders;

use Joinup\TaskRunner\Traits\SolrTrait;
use OpenEuropa\TaskRunner\Contract\ConfigProviderInterface;
use Robo\Config\Config;

/**
 * Ensures backwards compatibility for SOLR_* environment variables.
 *
 * @priority 1
 *
 * @todo Remove this config provider in ISAICP-7063.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7063
 */
class SolrBackwardsCompatibilityConfigProvider implements ConfigProviderInterface {

  /**
   * {@inheritdoc}
   */
  public static function provide(Config $config): void {
    SolrTrait::solrEnvVarsBackwardsCompatibility();
  }

}
