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
  public static function providerTestApiGuesser(): array {
    return [
      'GitLab home' => [
        'url' => 'https://gitlab.com/z.a.horvath/smart_title',
        'expectedApi' => [
          'pluginId' => 'gitlab_com',
          'baseUrl' => 'https://gitlab.com',
        ],
      ],
      'GitLab HTTP URL' => [
        'url' => 'https://gitlab.com/z.a.horvath/smart_title.git',
        'expectedApi' => [
          'pluginId' => 'gitlab_com',
          'baseUrl' => 'https://gitlab.com',
        ],
      ],

      'Self-hosted GitLab home' => [
        'url' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference',
        'expectedApi' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/subpath',
        ],
      ],
      'Self-hosted GitLab home with trailing slash' => [
        'url' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference/',
        'expectedApi' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/subpath',
        ],
      ],
      'Self-hosted GitLab HTTP URL' => [
        'url' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference.git',
        'expectedApi' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/subpath',
        ],
      ],

      'Self-hosted GitLab in deep subpath' => [
        'url' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath/ec-europa/digit-joinup-poc-reference/',
        'expectedApi' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath',
        ],
      ],

      'Self-hosted GitLab in root, with deep namespace' => [
        'url' => 'https://gitlab.opencode.de.example.com/xleitstelle/xplanung/validierungsregeln/profile/berlin',
        'expectedApi' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://gitlab.opencode.de.example.com',
        ],
      ],

      'Self-hosted GitLab in subpath, with deep namespace' => [
        'url' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath/deep/namespace/package',
        'expectedApi' => [
          'pluginId' => 'gitlab_self_hosted',
          'baseUrl' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath',
        ],
      ],
      'Self-hosted GitLab non-repository' => [
        'url' => 'https://self-hosted.gitlab.example.com/bit',
        'expectedApi' => NULL,
      ],
      'Self-hosted GitLab non-repository (host only)' => [
        'url' => 'https://self-hosted.gitlab.example.com',
        'expectedApi' => NULL,
      ],
      'Self-hosted GitLab non-repository (arbitrary path)' => [
        'url' => 'https://self-hosted.gitlab.example.com/foo/bar/baz/qux',
        'expectedApi' => NULL,
      ],

      'GitHub home' => [
        'url' => 'https://github.com/ec-europa/toolkit',
        'expectedApi' => [
          'pluginId' => 'github',
          'baseUrl' => 'https://github.com',
        ],
      ],
      'GitHub HTTP URL' => [
        'url' => 'https://github.com/ec-europa/toolkit.git',
        'expectedApi' => [
          'pluginId' => 'github',
          'baseUrl' => 'https://github.com',
        ],
      ],

      // Bitbucket.org.
      'Bitbucket home' => [
        'url' => 'https://bitbucket.org/atlassianlabs/docker-browse',
        'expectedApi' => [
          'pluginId' => 'bitbucket_org',
          'baseUrl' => 'https://bitbucket.org',
        ],
      ],
      'Bitbucket HTTP URL' => [
        'url' => 'https://bitbucket.org/atlassianlabs/docker-browse.git',
        'expectedApi' => [
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
  public static function providerTestApiGuesserExceptions(): array {
    return [
      'Invalid URL' => [
        'url' => 'sdivsdoifvsfo sftestApiGuessersdflvsdfv;∂ßńvsdfv',
        'expectedException' => \InvalidArgumentException::class,
      ],
    ];
  }

}
