<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\eu_oss_catalogue\Model\Project;
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
    foreach (parent::fetch() as $project) {
      if ($project->getFullName() === 'dummy-namespace-1/dummy-name-1') {
        $values = $project->toArray();
        $fileBlob = $values['files']['publiccodeYml'];
        $fileBlob = str_replace(
          [
            'name: GitHub solution 1',
            "- 'Solution_1_EN_Screenshot2.jpg'",
          ],
          [
            'name: GitHub solution 1 (updated)',
            "- 'Solution_1_EN_Screenshot2-updated.jpg'",
          ],
          $fileBlob
        );
        $values['files']['publiccodeYml'] = $fileBlob;
        $values['latestReleaseTag'] = '3.0.0';
        yield new Project(...$values);
        continue;
      }
      yield $project;
    }
  }

}
