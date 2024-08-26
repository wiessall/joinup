<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

/**
 * Provider to be used in testing.
 *
 * Same as developers_italia but we need a different name.
 *
 * @EuOssCatalogueProvider(
 *   id = "yaml_become_invalid_provider",
 *   label = "YAML become invalid",
 *   parser = "publiccode_yml",
 * )
 */
class YamlBecomeInvalidProvider extends SelectedProjectProvider {

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    $updateUrl = 'https://limited.self-hosted.gitlab.example.com/group1/project-1-1';
    $wasCreated = $this->wasCreated($updateUrl);

    foreach (parent::fetch() as $project) {
      if ($project->url === $updateUrl && $wasCreated) {
        yield $this->makeInvalid($project);
        continue;
      }

      yield $project;
    }
  }

}
