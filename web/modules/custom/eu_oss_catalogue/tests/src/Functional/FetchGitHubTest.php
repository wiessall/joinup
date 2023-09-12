<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\eu_oss_catalogue\Traits\EuOssCatalogueTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\eu_oss_catalogue\Entity\OssContactInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drush\TestTraits\DrushTestTrait;

/**
 * Fetch data from GitHub test.
 *
 * @group eu_oss_catalogue
 */
class FetchGitHubTest extends BrowserTestBase {

  use DrushTestTrait;
  use NodeCreationTrait;
  use EuOssCatalogueTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'eu_oss_catalogue_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test GitHub hosting platform.
   */
  public function testGitHubHostingPlatform(): void {
    $service = \Drupal::getContainer()->get('eu_oss_catalogue');
    $service->fetch('hosting_platform:github_mockup');

    $queue = \Drupal::queue('eu_oss_catalogue');

    // The queue contains the 2 solutions and the cleanup step.
    $this->assertSame(3, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    // Queue items have been released.
    $this->assertSame(0, $queue->numberOfItems());

    $solution1 = $this->getNodeByTitle('GitHub solution 1');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution1);
    $this->assertEquals(
      static::EXPECTED_SOLUTION1,
      $this->contentEntityToArray(entity: $solution1, includedProperties: static::SOLUTION_PROPERTIES_TO_CHECK)
    );

    $solution2 = $this->getNodeByTitle('GitHub solution 2');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution2);
    $this->assertEquals(
      static::EXPECTED_SOLUTION2,
      $this->contentEntityToArray(entity: $solution2, includedProperties: static::SOLUTION_PROPERTIES_TO_CHECK)
    );

    $contact = $this->getNodeByTitle('Pirate Initiative');
    $this->assertInstanceOf(OssContactInterface::class, $contact);
    $this->assertSame('oss_contact', $contact->bundle());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $data = Yaml::decode(file_get_contents(__DIR__ . '/../../fixtures/eu_oss_catalogue.hosting_platform.github_mockup.yml'));
    $this->config('eu_oss_catalogue.hosting_platform.github_mockup')
      ->setData($data)
      ->save();

    $settings['settings']['eu_oss_catalogue']['platform_github']['access_token'] = (object) [
      'value' => 'dummy-token',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    $this->createTestingTerms([
      'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause'],
      'oss_category' => [
        'website-builder',
        'digital-citizenship',
      ],
    ]);
  }

  /**
   * Solution properties we test the values of.
   *
   * @const string[]
   */
  protected const SOLUTION_PROPERTIES_TO_CHECK = [
    'oss_git_url',
    'oss_logo',
    'oss_publiccode_yml_version',
    'oss_release_date',
    'oss_software_version',
    'title',
    'type',
  ];

  /**
   * Expected values of GitHub solution 1.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION1 = [
    'title' => [['value' => 'GitHub solution 1']],
    'type' => [['target_id' => 'oss_solution']],
    'oss_git_url' => [
      [
        'uri' => 'https://github.com/dummy-namespace-1/dummy-name-1',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_logo' => [
      [
        'uri' => 'https://github.com/dummy-namespace-1/dummy-name-1/images/logo-solution-1.png',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_publiccode_yml_version' => [['value' => '0.2']],
    'oss_release_date' => [['value' => '2017-05-01']],
    'oss_software_version' => [['value' => '1.4']],
  ];

  /**
   * Expected values of GitHub solution 2.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION2 = [
    'title' => [['value' => 'GitHub solution 2']],
    'type' => [['target_id' => 'oss_solution']],
    'oss_git_url' => [
      [
        'uri' => 'https://github.com/dummy-namespace-2/dummy-name-2',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_logo' => [],
    'oss_publiccode_yml_version' => [['value' => '0.4']],
    'oss_release_date' => [['value' => '2016-02-09']],
    'oss_software_version' => [['value' => 'v2.4']],
  ];

}
