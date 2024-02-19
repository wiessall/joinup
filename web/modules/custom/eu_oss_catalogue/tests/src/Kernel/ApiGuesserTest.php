<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eu_oss_catalogue\ApiGuesser;
use Drupal\eu_oss_catalogue\Model\ApiGuess;
use Prophecy\Argument;

/**
 * Tests the OSS Solution API Guesser service.
 *
 * @group eu_oss_catalogue
 */
class ApiGuesserTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eu_oss_catalogue',
    'eu_oss_catalogue_test',
    'eu_oss_catalogue_test_not_allowed_mock',
    'http_request_mock',
  ];

  /**
   * The tested API Guesser service.
   */
  protected ApiGuesser $apiGuesser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->apiGuesser = $this->container->get('eu_oss_catalogue.git_remote.api_guesser');
  }

  /**
   * Tests the API Guesser.
   *
   * @param string $url
   *   The URL of a git repository.
   * @param array|null $expectedApi
   *   The expected result from the API Guesser service.
   *
   * @dataProvider providerTestApiGuesser
   */
  public function testApiGuesser(string $url, ?array $expectedApi): void {
    if ($expectedApi === NULL) {
      $logger = $this->prophesize(LoggerChannelInterface::class);
      $logger->warning()->withArguments([Argument::any()])->shouldBeCalledOnce();

      $this->apiGuesser = new ApiGuesser(
        $this->container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
        $this->container->get('state'),
        $logger->reveal()
      );
    }
    else {
      $expectedApi = new ApiGuess($expectedApi['pluginId'], $expectedApi['baseUrl']);
    }

    $this->assertEquals($expectedApi, $this->apiGuesser->getHostingPlatformApi($url));
  }

  /**
   * Data provider for ::testApiGuesser.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestApiGuesser(): array {
    return [
      'GitLab home' => [
        'URL' => 'https://gitlab.com/z.a.horvath/smart_title',
        'Expected' => [
          'pluginId' => 'gitlab_com',
          'baseUrl' => 'https://gitlab.com',
        ],
      ],
      'GitLab HTTP URL' => [
        'URL' => 'https://gitlab.com/z.a.horvath/smart_title.git',
        'Expected' => [
          'pluginId' => 'gitlab_com',
          'baseUrl' => 'https://gitlab.com',
        ],
      ],

      'Self-hosted GitLab home' => [
        'URL' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference',
        'Expected' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/subpath',
        ],
      ],
      'Self-hosted GitLab home with trailing slash' => [
        'URL' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference/',
        'Expected' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/subpath',
        ],
      ],
      'Self-hosted GitLab HTTP URL' => [
        'URL' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference.git',
        'Expected' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/subpath',
        ],
      ],

      'Self-hosted GitLab in deep subpath' => [
        'URL' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath/ec-europa/digit-joinup-poc-reference/',
        'Expected' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath',
        ],
      ],

      'Self-hosted GitLab in root, with deep namespace' => [
        'URL' => 'https://gitlab.opencode.de.example.com/xleitstelle/xplanung/validierungsregeln/profile/berlin',
        'Expected' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://gitlab.opencode.de.example.com',
        ],
      ],

      'Self-hosted GitLab in subpath, with deep namespace' => [
        'URL' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath/deep/namespace/package',
        'Expected' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath',
        ],
      ],
      'Self-hosted GitLab non-repository' => [
        'URL' => 'https://self-hosted.gitlab.example.com/bit',
        'Expected' => NULL,
      ],
      'Self-hosted GitLab non-repository (host only)' => [
        'URL' => 'https://self-hosted.gitlab.example.com',
        'Expected' => NULL,
      ],
      'Self-hosted GitLab non-repository (arbitrary path)' => [
        'URL' => 'https://self-hosted.gitlab.example.com/foo/bar/baz/qux',
        'Expected' => NULL,
      ],

      'GitHub home' => [
        'URL' => 'https://github.com/ec-europa/toolkit',
        'Expected' => [
          'pluginId' => 'github',
          'baseUrl' => 'https://github.com',
        ],
      ],
      'GitHub HTTP URL' => [
        'URL' => 'https://github.com/ec-europa/toolkit.git',
        'Expected' => [
          'pluginId' => 'github',
          'baseUrl' => 'https://github.com',
        ],
      ],

      // Bitbucket.org.
      'Bitbucket home' => [
        'URL' => 'https://bitbucket.org/atlassianlabs/docker-browse',
        'Expected' => [
          'pluginId' => 'bitbucket_org',
          'baseUrl' => 'https://bitbucket.org',
        ],
      ],
      'Bitbucket HTTP URL' => [
        'URL' => 'https://bitbucket.org/atlassianlabs/docker-browse.git',
        'Expected' => [
          'pluginId' => 'bitbucket_org',
          'baseUrl' => 'https://bitbucket.org',
        ],
      ],
    ];
  }

  /**
   * Tests exceptions thrown by API Guesser.
   *
   * @param string $url
   *   The URL of a git repository.
   * @param string $expectedException
   *   The expected exception.
   *
   * @dataProvider providerTestApiGuesserExceptions
   */
  public function testApiGuesserExceptions(string $url, string $expectedException): void {
    $this->expectException($expectedException);
    $this->apiGuesser->getHostingPlatformApi($url);
  }

  /**
   * Data provider for ::testApiGuesserExceptions.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestApiGuesserExceptions(): array {
    return [
      'Invalid URL' => [
        'URL' => 'sdivsdoifvsfo sftestApiGuessersdflvsdfv;∂ßńvsdfv',
        'Expected exception' => \InvalidArgumentException::class,
      ],
    ];
  }

}
