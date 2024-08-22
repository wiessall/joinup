<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

/**
 * Provider to be used in testing.
 *
 * Same as developers_italia but we need a different name.
 *
 * @EuOssCatalogueProvider(
 *   id = "removing_solution_provider",
 *   label = "Removing solution provider",
 *   parser = "publiccode_yml",
 * )
 */
class RemovingSolutionProvider extends SelectedProjectProvider {

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    $skipUrl = 'https://limited.self-hosted.gitlab.example.com/group1/project-1-1';
    $wasCreated = $this->wasCreated($skipUrl);

    foreach (parent::fetch() as $project) {
      // If the title is "Solution 2" and the solution exists, skip it so that
      // we simulate a solution that has been removed.
      if ($project->url === $skipUrl && $wasCreated) {
        continue;
      }

      yield $project;
    }
  }

}
