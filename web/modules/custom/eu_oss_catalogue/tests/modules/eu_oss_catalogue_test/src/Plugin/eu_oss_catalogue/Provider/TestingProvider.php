<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
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
  public function alterSolution(OssSolutionInterface $solution, EuOssCatalogueFetchedSolution $fetchedSolution): void {
    // Simulate that this provider draws a newer version of the solution.
    if ($solution->label() === 'Solution 1') {
      $solution->setTitle('Solution 1 (updated)');
      $solution->set('oss_software_version', '1.0.0.1');
    }
  }

}
