<?php

declare(strict_types = 1);

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
        $fileBlob = $project->files['publiccodeYml'];
        $fileBlob = str_replace('name: GitHub solution 1', 'name: GitHub solution 1 (updated)', $fileBlob);
        $fileBlob = str_replace("softwareVersion: '1.4'", "softwareVersion: '1.5'", $fileBlob);
        $project = new Project(
          $project->namespace,
          $project->name,
          $project->url,
          $project->defaultBranch,
          $project->latestReleaseTag,
          $project->latestReleaseDate,
          $project->platformReference,
          $project->description,
          ['publiccodeYml' => $fileBlob],
        );
      }

      yield $project;
    }
  }

}
