<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;
use Drupal\eu_oss_catalogue_test\Plugin\ServiceMock\PublicCode;

/**
 * Provider used to test handling of a validator API exception.
 */
#[EuOssCatalogueProvider(
  id: "api_exception_provider",
  label: new TranslatableMarkup("API exception"),
  parser: "publiccode_yml",
  type: CatalogueType::Local,
)]
class ApiExceptionProvider extends SelectedProjectProvider {

  /**
   * {@inheritdoc}
   */
  protected array $selectedProjects = [
    'https://limited.self-hosted.gitlab.example.com/group1/project-1-1',
  ];

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    foreach (parent::fetch() as $project) {
      yield $this->makeInvalid($project, PublicCode::EXCEPTION_YAML_MARKER);
    }
  }

}
