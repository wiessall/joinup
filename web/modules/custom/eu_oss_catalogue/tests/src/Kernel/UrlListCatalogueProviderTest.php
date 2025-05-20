<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\UrlListCatalogueProviderBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Tests the URL list provider base class.
 *
 * @coversDefaultClass \Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\UrlListCatalogueProviderBase
 */
class UrlListCatalogueProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'eu_oss_catalogue',
    'eu_oss_catalogue_test_not_allowed_mock',
    'http_request_mock',
  ];

  /**
   * Tests the plugin's fetch() method.
   *
   * @param string|null $apiResponse
   *   The API response to mock.
   * @param array $rootKeys
   *   Keys to get the solution list with the URLs.
   * @param array $urlKeys
   *   Keys to the solution url in the data list.
   * @param array $expectedResults
   *   The expected results.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
   *
   * @dataProvider providerFetchTest
   */
  public function testPlugin(
    string|null $apiResponse,
    array $rootKeys,
    array $urlKeys,
    array $expectedResults,
  ): void {
    $mock = new MockHandler([
      new Response(200, ['Content-Type' => 'application/json'], $apiResponse),
      new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'values' => [
          'foo' => 'bar',
        ],
      ])),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $httpClient = new Client(['handler' => $handlerStack]);

    $hostingPlatformMock = $this->prophesize(EuOssCatalogueHostingPlatformInterface::class);
    $hostingPlatformMock
      ->isHostingPlatformForUrl(Argument::type('string'))
      ->willReturn(TRUE);
    $hostingPlatformMock
      ->getBaseFromRepoUrl(Argument::type('string'))
      ->will(function ($args) {
        return implode('://', [
          parse_url($args[0], PHP_URL_SCHEME),
          parse_url($args[0], PHP_URL_HOST),
        ]);
      });
    $hostingPlatformMock
      ->getProjects(['publiccodeYml' => 'publiccode.yml'])
      ->willReturn([
        new Project(
          namespace: 'foo',
          name: 'bar',
          url: 'https://example.com',
          platform: 'testPlatform',
          defaultBranch: 'main',
          files: [
            'publiccodeYml' => [
              'faasfs' => 'sdivsdoifvj',
            ],
          ],
        ),
      ]);

    $hostingPlatformManagerMock = $this->prophesize(EuOssCataloguePluginManagerInterface::class);
    $hostingPlatformManagerMock
      ->getDefinitions()
      ->willReturn(['testPlatform' => ['id' => 'testPlatform']]);
    $hostingPlatformManagerMock
      ->createInstance(Argument::any(), Argument::cetera())
      ->willReturn($hostingPlatformMock->reveal());

    $this->container->set('plugin.manager.eu_oss_catalogue.hosting_platform', $hostingPlatformManagerMock->reveal());

    $plugin = new TestUrlListProvider(
      [
        'rootKeys' => $rootKeys,
        'urlKeys' => $urlKeys,
        'endpoints' => ['https://example.com/foo/bar-baz.json'],
      ],
      'test',
      [],
      $this->container->get('plugin.manager.eu_oss_catalogue.parser'),
      $this->container->get('logger.channel.eu_oss_catalogue'),
      $httpClient,
      $this->container->get('eu_oss_catalogue.git_remote.api_guesser'),
      $this->container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
    );

    $resultAsArray = iterator_to_array($plugin->fetch());
    $this->assertEquals($expectedResults, $resultAsArray);
  }

  /**
   * Test data provider for ::testFetch().
   *
   * @return array{'apiResponse': string|null, 'rootKeys': string[], 'urlKeys': string[], 'expectedResults': array}
   *   The test cases.
   */
  public static function providerFetchTest(): array {
    return [
      'sdfvsd' => [
        'apiResponse' => json_encode([
          'data' => [
            [
              'url' => 'https://bitbucket.org/foo/bar.git',
            ],
          ],
        ]),
        'rootKeys' => ['data'],
        'urlKeys' => ['url'],
        'expectedResults' => [],
      ],

      'French catalogue' => [
        'apiResponse' => json_encode([
          [
            'url' => 'https://bitbucket.org/foo/bar.git',
          ],
        ]),
        'rootKeys' => [],
        'urlKeys' => ['url'],
        'expectedResults' => [],
      ],
    ];
  }

}

/**
 * The OSS solution provider plugin class to test.
 */
class TestUrlListProvider extends UrlListCatalogueProviderBase {}
