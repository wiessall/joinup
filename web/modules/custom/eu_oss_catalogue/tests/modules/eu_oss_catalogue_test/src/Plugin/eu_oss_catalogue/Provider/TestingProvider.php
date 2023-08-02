<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\Provider;

use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\DevelopersItalia;

/**
 * Provider to be used in testing.
 *
 * Same as developers_italia but we need a different name.
 *
 * @EuOssCatalogueProvider(
 *   id = "testing_provider",
 *   label = "Testing provider",
 *   parser = "publiccode_yml",
 * )
 */
class TestingProvider extends DevelopersItalia {
}
