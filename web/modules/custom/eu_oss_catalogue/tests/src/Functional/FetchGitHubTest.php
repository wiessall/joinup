<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drush\TestTraits\DrushTestTrait;

/**
 * Fetch data from GitHub test.
 *
 * @group eu_oss_catalogue
 */
class FetchGitHubTest extends BrowserTestBase {

  use DrushTestTrait;
  use NodeCreationTrait;

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
    $this->assertSame(3, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);
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
  }

}
