<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\eu_oss_catalogue\Traits\EuOssCatalogueTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the GitLab code hosting provider.
 */
class FetchCitLabTest extends BrowserTestBase {

  use DrushTestTrait;
  use NodeCreationTrait;
  use EuOssCatalogueTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'eu_oss_catalogue_test',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createTestingTerms([
      'oss_licence' => ['AGPL-3.0-or-later', 'MIT', 'EUPL-1.2'],
      'oss_category' => ['data-collection', 'data-analytics', 'nothing'],
    ]);
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Tests fetching all solutions from a GitLab self-hosting platform.
   */
  public function testSelfHostedAll(): void {
    \Drupal::service('eu_oss_catalogue')->fetch('hosting_platform:gitlab_self_hosted_all');

    // Check how incoming data has been queued.
    $queue = \Drupal::queue('eu_oss_catalogue');
    // The queue contains the 2 solutions and the cleanup step.
    $this->assertSame(3, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    // Queue items have been released.
    $this->assertSame(0, $queue->numberOfItems());

    $solution1 = $this->getNodeByTitle('Project 1');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution1);
    $this->assertEquals(
      static::EXPECTED_SOLUTION1,
      $this->contentEntityToArray($solution1, [], static::SOLUTION_PROPERTIES_TO_CHECK),
    );

    $solution2 = $this->getNodeByTitle('Project 2');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution1);
    $this->assertEquals(
      static::EXPECTED_SOLUTION2,
      $this->contentEntityToArray($solution2, [], static::SOLUTION_PROPERTIES_TO_CHECK)['en'],
    );
  }

  /**
   * Tests fetching some solutions from a GitLab self-hosting platform.
   */
  public function testSelfHostedLimited(): void {
    \Drupal::service('eu_oss_catalogue')->fetch('hosting_platform:gitlab_self_hosted_limited');

    // Check how incoming data has been queued.
    $queue = \Drupal::queue('eu_oss_catalogue');
    // The queue contains the 4 solutions and the cleanup step.
    $this->assertSame(5, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    // Queue items have been released.
    $this->assertSame(0, $queue->numberOfItems());

    $solution1 = $this->getNodeByTitle('Project 1.1');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution1);
    $this->assertEquals(
      static::EXPECTED_SOLUTION3,
      $this->contentEntityToArray($solution1, [], static::SOLUTION_PROPERTIES_TO_CHECK)['en'],
    );

    $solution2 = $this->getNodeByTitle('Project 1.2');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution2);
    $this->assertEquals(
      static::EXPECTED_SOLUTION4,
      $this->contentEntityToArray($solution2, [], static::SOLUTION_PROPERTIES_TO_CHECK)['en'],
    );

    $solution3 = $this->getNodeByTitle('Project 2.1');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution3);
    $this->assertEquals(
      static::EXPECTED_SOLUTION5,
      $this->contentEntityToArray($solution3, [], static::SOLUTION_PROPERTIES_TO_CHECK)['en'],
    );

    $solution4 = $this->getNodeByTitle('Project 1');
    $this->assertInstanceOf(OssSolutionInterface::class, $solution4);
    $this->assertEquals(
      static::EXPECTED_SOLUTION6,
      $this->contentEntityToArray($solution4, [], static::SOLUTION_PROPERTIES_TO_CHECK)['en'],
    );
  }

  /**
   * Solution properties we test the values of.
   *
   * @const string[]
   */
  protected const SOLUTION_PROPERTIES_TO_CHECK = [
    'oss_git_url',
    'oss_publiccode_yml_version',
    'oss_release_date',
    'oss_software_version',
  ];

  /**
   * Expected values of GitHub solution 1.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION1 = [
    'oss_git_url' => [
      [
        'uri' => 'https://all.self-hosted.gitlab.example.com/group1/group1_2/group1_3/project1',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_publiccode_yml_version' => [['value' => '0.2']],
    'oss_release_date' => [['value' => '2022-10-07']],
    'oss_software_version' => [['value' => '1.4.5']],
  ];

  /**
   * Expected values of GitHub solution 2.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION2 = [
    'oss_git_url' => [
      [
        'uri' => 'https://all.self-hosted.gitlab.example.com/group2/group2_2/group2_3/project2',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_publiccode_yml_version' => [['value' => '0.2']],
    'oss_release_date' => [['value' => '2021-08-05']],
    'oss_software_version' => [['value' => '7.0.2-alpha1']],
  ];

  /**
   * Expected values of GitHub solution 3.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION3 = [
    'oss_git_url' => [
      [
        'uri' => 'https://limited.self-hosted.gitlab.example.com/group1/project-1-1',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_publiccode_yml_version' => [['value' => '0.2']],
    'oss_release_date' => [['value' => '2022-12-23']],
    'oss_software_version' => [['value' => '1.0.2']],
  ];

  /**
   * Expected values of GitHub solution 4.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION4 = [
    'oss_git_url' => [
      [
        'uri' => 'https://limited.self-hosted.gitlab.example.com/group1/project-1-2',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_publiccode_yml_version' => [['value' => '0.2']],
    'oss_release_date' => [['value' => '2022-12-16']],
    'oss_software_version' => [['value' => '1.0.0']],
  ];

  /**
   * Expected values of GitHub solution 5.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION5 = [
    'oss_git_url' => [
      [
        'uri' => 'https://limited.self-hosted.gitlab.example.com/group2/project-2-1',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_publiccode_yml_version' => [['value' => '0.2']],
    'oss_release_date' => [['value' => '2020-07-19']],
    'oss_software_version' => [['value' => '7.0.1']],
  ];

  /**
   * Expected values of GitHub solution 6.
   *
   * @const array[]
   */
  protected const EXPECTED_SOLUTION6 = [
    'oss_git_url' => [
      [
        'uri' => 'https://limited.self-hosted.gitlab.example.com/user1/project-1',
        'title' => NULL,
        'options' => [],
      ],
    ],
    'oss_publiccode_yml_version' => [['value' => '0.2']],
    'oss_release_date' => [['value' => '2010-02-18']],
    'oss_software_version' => [['value' => '7.0.1-beta2']],
  ];

}
