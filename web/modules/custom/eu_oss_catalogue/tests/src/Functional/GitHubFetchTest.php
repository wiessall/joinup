<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\eu_oss_catalogue\Entity\OssContactInterface;
use Drupal\eu_oss_catalogue\Entity\OssContractorInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Fetch data from GitHub test.
 *
 * @group eu_oss_catalogue
 */
class GitHubFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause'],
    'oss_category' => ['website-builder', 'digital-citizenship'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings['settings']['eu_oss_catalogue']['platform_github']['access_token'] = (object) [
      'value' => 'dummy-token',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Test GitHub hosting platform.
   */
  public function testGitHubHostingPlatform(): void {
    $this->fetchSolutions('hosting_platform:github_mockup', 2);

    $this->assertNode('GitHub solution 1', OssSolutionInterface::class);
    $this->assertNode('FOSS Conference', OssContactInterface::class);
    $this->assertNode('Pirate Initiative', OssContactInterface::class);

    $this->assertNode('GitHub solution 2', OssSolutionInterface::class);
    $this->assertNode('ACME Ltd', OssContractorInterface::class);
    $this->assertNode('Das Auto GmbH', OssContractorInterface::class);
    $this->assertNode('Peter Gregory', OssContactInterface::class);
  }

}
