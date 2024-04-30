<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test;

/**
 * HTTP Request Mock pluguins reusable code.
 */
trait IntegrationTestTrait {

  /**
   * Checks whether the plugin is called in an integration test.
   *
   * @return bool
   *   Whether the plugin is called in an integration test.
   */
  protected function isIntegrationTest(): bool {
    return \Drupal::state()->get('eu_oss_catalogue_test.integration_test', FALSE);
  }

}
