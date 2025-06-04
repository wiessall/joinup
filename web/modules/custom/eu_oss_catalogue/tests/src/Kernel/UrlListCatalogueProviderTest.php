<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eu_oss_catalogue\ApiGuesserInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\Model\ApiGuess;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\UrlListCatalogueProviderBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ProphecyInterface;

/**
 * Tests the URL list provider base class.
 *
 * @coversDefaultClass \Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\UrlListCatalogueProviderBase
 */
class UrlListCatalogueProviderTest extends KernelTestBase {

  /**
   * The mocked hosting platform manager.
   */
  protected ProphecyInterface|EuOssCataloguePluginManagerInterface $hostingPlatformManagerMock;

  /**
   * API guesser.
   */
  protected ProphecyInterface|ApiGuesserInterface $apiGuesserMock;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eu_oss_catalogue',
    // Make sure that we don't send any request to external services.
    'eu_oss_catalogue_test_not_allowed_mock',
    'http_request_mock',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $hostingPlatformMock = $this->buildHostingPlatformMock();

    $this->hostingPlatformManagerMock = $this->prophesize(EuOssCataloguePluginManagerInterface::class);
    $this->hostingPlatformManagerMock
      ->getDefinitions()
      ->willReturn(['testPlatform' => ['id' => 'testPlatform']]);
    $this->hostingPlatformManagerMock
      ->createInstance(Argument::any(), Argument::cetera())
      ->will(
        function ($args) use ($hostingPlatformMock): EuOssCatalogueHostingPlatformInterface {
          $hostingPlatform = $hostingPlatformMock->reveal();
          $hostingPlatform->setConfiguration($args[1]);
          return $hostingPlatform;
        }
      );

    $this->apiGuesserMock = $this->prophesize(ApiGuesserInterface::class);
    $this->apiGuesserMock
      ->getHostingPlatformApi(Argument::type('string'))
      ->will(function ($args): ?ApiGuess {
        return new ApiGuess('testPlatform', $args[0]);
      });
  }

  /**
   * Tests the plugin's fetch() method.
   *
   * @param array $apiResponses
   *   The API responses to mock. Should follow the same order as the endpoints'
   *   order in the plugin configuration.
   * @param array $pluginConfiguration
   *   The configuration of the plugin.
   * @param array $expectedResults
   *   The expected results (projects) as array.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
   *
   * @dataProvider providerFetchTest
   */
  public function testPlugin(
    array $apiResponses,
    array $pluginConfiguration,
    array $expectedResults,
  ): void {
    $mockHandler = new MockHandler();
    array_map(
      function (string $responseData) use ($mockHandler): void {
        $mockHandler->append(
          new Response(
            200,
            ['Content-Type' => 'application/json'],
            $responseData,
          )
        );
      },
      $apiResponses,
    );
    $handlerStack = HandlerStack::create($mockHandler);
    $httpClient = new Client(['handler' => $handlerStack]);

    $plugin = new TestUrlListProvider(
      $pluginConfiguration,
      'test',
      [
        'label' => new TranslatableMarkup('Test provider'),
      ],
      $this->container->get('plugin.manager.eu_oss_catalogue.parser'),
      $this->container->get('logger.channel.eu_oss_catalogue'),
      $httpClient,
      $this->apiGuesserMock->reveal(),
      $this->hostingPlatformManagerMock->reveal(),
    );

    $projectsArray = iterator_to_array($plugin->fetch());
    $resultAsArray = array_map(
      fn (Project $project) => array_filter($project->toArray()),
      $projectsArray,
    );
    $this->assertEquals($expectedResults, $resultAsArray);
  }

  /**
   * Test data provider for ::testFetch().
   *
   * @return array{apiResponse: string|null, rootKeys: string[], urlKeys: string[], expectedResults: array[]}
   *   The test cases.
   */
  public static function providerFetchTest(): array {
    return [
      'Data root, URL key' => [
        'apiResponses' => [
          json_encode([
            'data' => [
              [
                'url' => 'https://example.com/foo/bar.git',
              ],
              [
                'url' => 'https://example.com/foo/bar',
              ],
            ],
          ]),
        ],
        'pluginConfiguration' => [
          'endpoints' => [
            [
              'apiUrl' => 'https://example.com/foo/bar.git',
              'rootKeys' => ['data'],
              'urlKeys' => ['url'],
            ],
          ],
        ],
        'expectedResults' => [
          [
            'namespace' => 'foo',
            'name' => 'bar',
            'url' => 'https://example.com/foo/bar.git',
            'platform' => 'testPlatform',
            'defaultBranch' => 'main',
            'files' => ['publiccodeYml' => "name: 'foo/bar'\n"],
          ],
        ],
      ],

      'Direct root, nested URL' => [
        'apiResponses' => [
          json_encode([
            ['nested' => ['url' => 'https://example.com/bar/baz']],
          ]),
        ],
        'pluginConfiguration' => [
          'endpoints' => [
            [
              'apiUrl' => 'https://example.com/foo/bar/baz',
              'rootKeys' => [],
              'urlKeys' => ['nested', 'url'],
            ],
          ],
        ],
        'expectedResults' => [
          [
            'namespace' => 'bar',
            'name' => 'baz',
            'url' => 'https://example.com/bar/baz',
            'platform' => 'testPlatform',
            'defaultBranch' => 'main',
            'files' => ['publiccodeYml' => "name: 'bar/baz'\n"],
          ],
        ],
      ],

      'Multiple endpoints with different structure' => [
        'apiResponses' => [
          'wrong keys' => json_encode([
            'root' => [['url' => 'https://example.com/wrongNS/wrongProject']],
          ]),
          'foo' => json_encode([
            'fooRoot' => [['fooUrl' => 'https://example.com/fooNS/fooProject']],
          ]),
          'bar' => json_encode([
            'barRoot' => [['barUrl' => 'https://example.com/barNs/barProject']],
          ]),
        ],
        'pluginConfiguration' => [
          'endpoints' => [
            'wrong keys' => [
              'apiUrl' => 'https://example.com/wrong.json',
              'rootKeys' => ['wrongRoot'],
              'urlKeys' => ['url'],
            ],
            'foo' => [
              'apiUrl' => 'https://example.com/foo.json',
              'rootKeys' => ['fooRoot'],
              'urlKeys' => ['fooUrl'],
            ],
            'bar' => [
              'apiUrl' => 'https://example.com/bar.json',
              'rootKeys' => ['barRoot'],
              'urlKeys' => ['barUrl'],
            ],
          ],
        ],
        'expectedResults' => [
          [
            'namespace' => 'fooNS',
            'name' => 'fooProject',
            'url' => 'https://example.com/fooNS/fooProject',
            'platform' => 'testPlatform',
            'defaultBranch' => 'main',
            'files' => ['publiccodeYml' => "name: 'fooNS/fooProject'\n"],
          ],
          [
            'namespace' => 'barNs',
            'name' => 'barProject',
            'url' => 'https://example.com/barNs/barProject',
            'platform' => 'testPlatform',
            'defaultBranch' => 'main',
            'files' => ['publiccodeYml' => "name: 'barNs/barProject'\n"],
          ],
        ],
      ],
    ];
  }

  /**
   * Builds a hosting platform mock.
   *
   * @return \Prophecy\Prophecy\ProphecyInterface|\Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface
   *   A hosting platform object phropecy which can be used for testing provider
   *   plugins.
   */
  private function buildHostingPlatformMock(): ProphecyInterface|EuOssCatalogueHostingPlatformInterface {
    $platformConfig = (object) [];

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
      ->setConfiguration(Argument::type('array'))
      ->will(function ($args) use ($platformConfig): void {
        foreach ($args[0] as $key => $val) {
          $platformConfig->$key = $val;
        }
      });
    $hostingPlatformMock
      ->getProjects(Argument::type('array'))
      ->will(function () use ($platformConfig): iterable {
        foreach ($platformConfig->projects as $ns => $names) {
          foreach ($names as $name) {
            yield new Project(
              namespace: $ns,
              name: $name,
              url: $platformConfig->baseUrl,
              platform: 'testPlatform',
              defaultBranch: 'main',
              files: [
                'publiccodeYml' => "name: '$ns/$name'\n",
              ],
            );
          }
        }
      });

    return $hostingPlatformMock;
  }

}

/**
 * The OSS solution provider plugin class to test.
 */
class TestUrlListProvider extends UrlListCatalogueProviderBase {}
