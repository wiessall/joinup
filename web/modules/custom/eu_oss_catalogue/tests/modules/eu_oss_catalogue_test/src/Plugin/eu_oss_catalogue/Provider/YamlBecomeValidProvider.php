<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;

/**
 * Provider to be used in testing.
 *
 * Same as developers_italia but we need a different name.
 */
#[EuOssCatalogueProvider(
  id: "yaml_become_valid_provider",
  label: new TranslatableMarkup("YAML become valid"),
  parser: "publiccode_yml",
  type: CatalogueType::Local,
)]
class YamlBecomeValidProvider extends SelectedProjectProvider {

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    $updateUrl = 'https://limited.self-hosted.gitlab.example.com/group1/project-1-1';
    // The gitlab project is never created when its YAML is API-invalid, so
    // use the github project (always valid) as the first-pass indicator.
    $firstPassDone = $this->wasCreated('https://github.com/dummy-namespace-1/dummy-name-1');

    foreach (parent::fetch() as $project) {
      if ($project->url === $updateUrl && !$firstPassDone) {
        yield $this->makeInvalid($project);
        continue;
      }

      yield $project;
    }
  }

}
