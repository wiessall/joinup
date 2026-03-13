<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;

/**
 * Provider used to test that updates are skipped when the API returns invalid.
 *
 * On the first fetch both projects are yielded as-is (valid). On subsequent
 * fetches the gitlab project's YAML is replaced with the API-invalid marker so
 * the mock validator returns invalid, while the hash change ensures the
 * solution is still queued for re-processing.
 */
#[EuOssCatalogueProvider(
  id: "api_invalid_update_provider",
  label: new TranslatableMarkup("API invalid (existing solution update)"),
  parser: "publiccode_yml",
  type: CatalogueType::Local,
)]
class ApiInvalidUpdateProvider extends SelectedProjectProvider {

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
