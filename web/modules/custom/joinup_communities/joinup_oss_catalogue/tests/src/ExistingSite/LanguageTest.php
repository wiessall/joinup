<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_oss_catalogue\ExistingSite;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests OSS solution language and scheduled translation.
 */
#[Group('eu_oss_catalogue')]
#[RunTestsInSeparateProcesses]
class LanguageTest extends JoinupExistingSiteTestBase {

  /**
   * Tests OSS solution language.
   */
  #[DataProvider('projectsProvider')]
  public function testOssSolutionLanguage(
    array $projectData,
    string $langcode,
    ?array $projectDataUpdated = NULL,
    ?string $langcodeUpdated = NULL,
  ): void {
    $parser = \Drupal::service('plugin.manager.eu_oss_catalogue.parser')->createInstance('publiccode_yml');
    $provider = \Drupal::service('plugin.manager.eu_oss_catalogue.provider')->createInstance('developers_italia');
    $project = new Project(...$projectData);

    $solution = $parser->parse($project, $provider);
    assert($solution instanceof OssSolutionInterface);
    $solution->save();
    $this->markEntityForCleanup($solution);

    $this->assertSame($langcode, $solution->language()->getId());
    $this->assertTrue($solution->getTranslation($langcode)->isDefaultTranslation());

    // Tests updating an existing solution.
    if ($projectDataUpdated) {
      $project = new Project(...$projectDataUpdated);
      $solution = $parser->parse($project, $provider);
      assert($solution instanceof OssSolutionInterface);
      $this->markEntityForCleanup($solution);

      $solution->save();
      $solution = Node::load($solution->id());

      $this->assertSame($langcodeUpdated, $solution->language()->getId());
      $this->assertTrue($solution->getTranslation($langcodeUpdated)->isDefaultTranslation());
    }
  }

  /**
   * Test cases provider for testOssSolutionLanguage().
   */
  public static function projectsProvider(): iterable {
    $yaml = file_get_contents(__DIR__ . '/../../fixtures/testing_projects.yml');
    foreach (Yaml::decode($yaml)['cases'] as $label => $data) {
      // Fill in mandatory fields not relevant for this test.
      $data['projectData'] = [
        'namespace' => 'foo',
        'name' => 'bar',
        'url' => 'https://github.com',
        'platform' => 'github',
        'defaultBranch' => 'main',
        'files' => ['publiccodeYml' => Yaml::encode($data['publiccodeYml'])],
      ];
      unset($data['publiccodeYml']);

      if (isset($data['publiccodeYmlUpdated'])) {
        $data['projectDataUpdated'] = [
          'namespace' => 'foo',
          'name' => 'bar',
          'url' => 'https://github.com',
          'platform' => 'github',
          'defaultBranch' => 'main',
          'files' => ['publiccodeYml' => Yaml::encode($data['publiccodeYmlUpdated'])],
        ];
        unset($data['publiccodeYmlUpdated']);
      }

      yield $label => $data;
    }
  }

}
