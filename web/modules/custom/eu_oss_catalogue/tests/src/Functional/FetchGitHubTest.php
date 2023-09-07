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

    // The queue contains the 2 solutions and the cleanup step.
    $this->assertSame(3, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution2 */
    $solution2 = $this->getNodeByTitle('GitHub solution 2');
    $this->assertNotFalse($solution2);
    $this->assertSame('oss_solution', $solution2->bundle());
    $this->assertSame('http://example.com/vendor/solution2.git', $solution2->get('oss_git_url')->uri);
    $this->assertSame('v2.4', $solution2->get('oss_software_version')->value);
    $this->assertSame('0.4', $solution2->get('oss_publiccode_yml_version')->value);
    $this->assertSame('2016-02-09', $solution2->get('oss_release_date')->value);
    $this->assertNull($solution2->get('oss_logo')->uri);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssContactInterface $contact */
    $contact = $this->getNodeByTitle('Pirate Initiative');
    $this->assertNotFalse($contact);
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
  }

}
