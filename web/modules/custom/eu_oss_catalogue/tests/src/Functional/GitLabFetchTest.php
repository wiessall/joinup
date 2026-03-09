<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\eu_oss_catalogue\Entity\CatalogueType;
use Drupal\eu_oss_catalogue\Entity\HostingPlatformConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the GitLab code hosting provider.
 */
#[Group('eu_oss_catalogue')]
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
    $this->assertOssSolution('Project 1', 'en');
    $this->assertOssSolution('Project 2', 'de');
    $this->assertOssSolution('Project 3', 'en');

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
    $this->fetchSolutions('hosting_platform:gitlab_self_hosted_limited', 7);
    $this->assertOssSolution('Project 1.1', 'it');
    $this->assertOssSolution('Project 1.2', 'de');
    $this->assertOssSolution('Project 1.3', 'de');
    $this->assertOssSolution('Project 2.1', 'de');
    $this->assertOssSolution('Project 1', 'de');

    // Check that 'logo not found' exception has been logged.
    $notFoundLogEntries = $this->getLogEntries(
      '%type: @message in %function (line %line of %file).',
      'a:6:{s:5:"%type";s:36:"GuzzleHttp\Exception\ClientException";s:8:"@message";s:152:"Client error: `HEAD https://limited.self-hosted.gitlab.example.com/group1/project-1-2/raw/HEAD/doesn-t/exist.png` resulted in a `404 Not Found` response";',
    );
    $this->assertCount(1, $notFoundLogEntries);
  }

  /**
   * Tests fetching a GitLab server which API endpoint redirects to HTML.
   */
  public function testWrongClientResponse(): void {
    $config = HostingPlatformConfig::create([
      'id' => 'gitlab_self_hosted_wrong',
      'label' => 'GitLab self-hosted (wrong)',
      'type' => CatalogueType::Local->value,
      'platformId' => 'gitlab_self_hosted',
      'platformSettings' => [
        'baseUrl' => 'https://wrong.self-hosted.gitlab.example.com',
        'projects' => ['group' => NULL],
      ],
    ]);
    $config->save();
    $this->fetchSolutions('hosting_platform:gitlab_self_hosted_wrong', -1);

    $this->assertEquals(
      [
        [
          'message' => "Can't get the group @namespace from @url. Message: @message",
          'variables' => [
            '%type' => 'RuntimeException',
            '@message' => 'Response returned by GraphQL client is not an array.',
            '%function' => 'Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform\GitLabDotCom->doGetProjects()',
            '@namespace' => 'group',
            '@url' => 'https://wrong.self-hosted.gitlab.example.com',
          ],
        ],
        [
          'message' => "Unstable API behind '@provider' provider plugin, skipping cleanup. Exceptions:\n@exception-messages",
          'variables' => [
            '@provider' => 'hosting_platform:gitlab_self_hosted_wrong',
            '@exception-messages' => 'Response returned by GraphQL client is not an array.',
          ],
        ],
      ],
      array_map(
        function (object $log): array {
          $logArray = (array) $log;
          $logArray['variables'] = unserialize($logArray['variables'], ['allowed_classes' => FALSE]);
          unset($logArray['variables']['%file']);
          unset($logArray['variables']['%line']);
          unset($logArray['variables']['@backtrace_string']);
          return $logArray;
        },
        $this->getLogEntries()
      )
    );
  }

}
