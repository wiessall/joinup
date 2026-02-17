<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\DevelopersItalia;

/**
 * Provider to be used in testing.
 *
 * Same as developers_italia but we need a different name.
 */
#[EuOssCatalogueProvider(
  id: "testing_provider",
  label: new TranslatableMarkup("Testing provider"),
  parser: "publiccode_yml",
  type: CatalogueType::Local,
)]
class TestingProvider extends DevelopersItalia {

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    foreach (parent::fetch() as $project) {
      if ($project->getFullName() === 'dummy-namespace-1/dummy-name-1') {
        $values = $project->toArray();

        $publicCode = Yaml::decode($values['files']['publiccodeYml']);
        $publicCode['name'] .= ' (updated)';
        $publicCode['maintenance'] = [];

        $values['files']['publiccodeYml'] = Yaml::encode($publicCode);
        $values['latestReleaseTag'] = '3.0.0';
        yield new Project(...$values);
        continue;
      }
      yield $project;
    }
  }

}
