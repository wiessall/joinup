<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Tests the GitLab code hosting provider.
 *
 * @group eu_oss_catalogue
 */
class GitLabFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'MIT', 'EUPL-1.2'],
    'oss_category' => ['data-collection', 'data-analytics', 'nothing'],
  ];

  /**
   * Tests fetching all solutions from a GitLab self-hosting platform.
   */
  public function testSelfHostedAll(): void {
    $this->fetchSolutions('hosting_platform:gitlab_self_hosted_all', 3);
    $this->assertNode('Project 1', OssSolutionInterface::class);
    $this->assertNode('Project 2', OssSolutionInterface::class);
    $this->assertNode('Project 3', OssSolutionInterface::class);

    // Check that 'logo not found' exception has been logged.
    $notFoundLogEntries = $this->getLogEntries(
      '%type: @message in %function (line %line of %file).',
      'a:6:{s:5:"%type";s:36:"GuzzleHttp\Exception\ClientException";s:8:"@message";s:181:"Client error: `HEAD https://all.self-hosted.gitlab.example.com/group2/group2_2/group2_3/project2/raw/HEAD/path/to/non-existing/image/logo.gif` resulted in a `404 Not Found` response";',
    );
    $this->assertCount(1, $notFoundLogEntries);
  }

  /**
   * Tests fetching limited list with solutions from a GitLab platform.
   *
   * This covers gitlab.com code hosting provider but also any self-hosted
   * instance with limited projects.
   */
  public function testLimitedList(): void {
    $this->fetchSolutions('hosting_platform:gitlab_self_hosted_limited', 5);
    $this->assertNode('Project 1.1', OssSolutionInterface::class);
    $this->assertNode('Project 1.2', OssSolutionInterface::class);
    $this->assertNode('Project 1.3', OssSolutionInterface::class);
    $this->assertNode('Project 2.1', OssSolutionInterface::class);
    $this->assertNode('Project 1', OssSolutionInterface::class);

    // Check that 'logo not found' exception has been logged.
    $notFoundLogEntries = $this->getLogEntries(
      '%type: @message in %function (line %line of %file).',
      'a:6:{s:5:"%type";s:36:"GuzzleHttp\Exception\ClientException";s:8:"@message";s:152:"Client error: `HEAD https://limited.self-hosted.gitlab.example.com/group1/project-1-2/raw/HEAD/doesn-t/exist.png` resulted in a `404 Not Found` response";',
    );
    $this->assertCount(1, $notFoundLogEntries);
  }

}
