<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\eu_oss_catalogue\Model\EuOssCatalogueFetchedSolution;
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

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    foreach (parent::fetch() as $fetchedSolution) {
      if ($fetchedSolution->name === 'Solution 1') {
        $data = [
          'name' => 'Solution 1 (updated)',
          'softwareVersion' => '1.0.0.1',
        ] + $fetchedSolution->data;
        $fetchedSolution = new EuOssCatalogueFetchedSolution(
          $data['url'],
          $data['name'],
          $data,
          $fetchedSolution->providerReference,
        );
      }

      yield $fetchedSolution;
    }
  }

}
