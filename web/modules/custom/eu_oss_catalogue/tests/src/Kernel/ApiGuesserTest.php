<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eu_oss_catalogue\ApiGuesser;
use Drupal\eu_oss_catalogue_test\Plugin\ServiceMock\Bitbucket;
use Prophecy\Argument;
use Psr\Log\LogLevel;

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
   *
   * @var \Drupal\eu_oss_catalogue\ApiGuesser
   */
  protected ApiGuesser $apiGuesser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container->get('state')->set(
      Bitbucket::BITBUCKET_MOCK_STATE_KEY,
      [
        'self-hosted.bitbucket.example.com' => [
          'atlassianlabs' => [
            'docker-browse',
          ],
        ],
        'bitbucket.org' => [
          'ec-europa' => [
            'digit-joinup-reference',
          ],
        ],
      ]
    );

    $this->apiGuesser = $this->container->get('eu_oss_catalogue.git_remote.api_guesser');
  }

  /**
   * Tests the API Guesser.
   *
   * @param string $url
   *   The URL of a git repository.
   * @param string|null $expected_api
   *   The expected result from the API Guesser service.
   *
   * @dataProvider providerTestApiGuesser
   */
  public function testApiGuesser(string $url, ?string $expected_api): void {
    if ($expected_api === NULL) {
      $logger = $this->prophesize(LoggerChannelInterface::class);
      $logger->log()->withArguments([
        Argument::exact(LogLevel::WARNING),
        Argument::any(),
      ])->shouldBeCalledOnce();

      $this->apiGuesser = new ApiGuesser(
        $this->container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
        $this->container->get('state'),
        $logger->reveal()
      );
    }

    $this->assertEquals(
      $expected_api,
      $this->apiGuesser->getHostingPlatformPluginId($url)
    );
  }

  /**
   * Data provider for ::testApiGuesser.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestApiGuesser(): array {
    return [
      'Only Gitlab' => [
        'URL' => 'https://gitlab.com',
        'Expected' => 'gitlab_com',
      ],
      'Gitlab home' => [
        'URL' => 'https://gitlab.com/z.a.horvath/smart_title',
        'Expected' => 'gitlab_com',
      ],
      'Gitlab HTTP URL' => [
        'URL' => 'https://gitlab.com/z.a.horvath/smart_title.git',
        'Expected' => 'gitlab_com',
      ],
      'Gitlab SSH URL' => [
        'URL' => 'git@gitlab.com:z.a.horvath/smart_title.git',
        'Expected' => 'gitlab_com',
      ],

      'Self-hosted Gitlab home' => [
        'URL' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference',
        'Expected' => 'gitlab_self_hosted',
      ],
      'Self-hosted Gitlab home with trailing slash' => [
        'URL' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference/',
        'Expected' => 'gitlab_self_hosted',
      ],
      'Self-hosted Gitlab HTTP URL' => [
        'URL' => 'https://self-hosted.gitlab.example.com/subpath/ec-europa/digit-joinup-reference.git',
        'Expected' => 'gitlab_self_hosted',
      ],
      'Self-hosted Gitlab SSH URL' => [
        'URL' => 'git@self-hosted.gitlab.example.com/subpath:ec-europa/digit-joinup-reference.git',
        'Expected' => 'gitlab_self_hosted',
      ],

      'Self-hosted Gitlab in deep subpath' => [
        'URL' => 'https://self-hosted.gitlab.example.com/bit/deeper/subpath/ec-europa/digit-joinup-poc-reference/',
        'Expected' => 'gitlab_self_hosted',
      ],

      'Only GitHub' => [
        'URL' => 'https://github.com',
        'Expected' => 'github',
      ],
      'GitHub home' => [
        'URL' => 'https://github.com/ec-europa/toolkit',
        'Expected' => 'github',
      ],
      'GitHub HTTP URL' => [
        'URL' => 'https://github.com/ec-europa/toolkit.git',
        'Expected' => 'github',
      ],
      'GitHub SSH URL' => [
        'URL' => 'git@github.com:ec-europa/toolkit.git',
        'Expected' => 'github',
      ],

      // Bitbucket isn't yet implemented.
      'Bitbucket home' => [
        'URL' => 'https://bitbucket.example.com/atlassianlabs/docker-browse',
        'Expected' => NULL,
      ],
      'Bitbucket HTTP URL' => [
        'URL' => 'https://bitbucket.example.com/atlassianlabs/docker-browse.git',
        'Expected' => NULL,
      ],
      'Bitbucket SSH URL' => [
        'URL' => 'git@bitbucket.example.com:atlassianlabs/docker-browse.git',
        'Expected' => NULL,
      ],
    ];
  }

  /**
   * Tests exceptions thrown by API Guesser.
   *
   * @param string $url
   *   The URL of a git repository.
   * @param string $expected_exception
   *   The expected exception.
   *
   * @dataProvider providerTestApiGuesserExceptions
   */
  public function testApiGuesserExceptions(string $url, string $expected_exception): void {
    $this->expectException($expected_exception);
    $this->apiGuesser->getHostingPlatformPluginId($url);
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
