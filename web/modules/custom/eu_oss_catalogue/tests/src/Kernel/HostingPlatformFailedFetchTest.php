<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\eu_oss_catalogue\Traits\LoggerTestTrait;
use Drupal\Tests\eu_oss_catalogue\Traits\SimpleResponseMockTrait;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use GuzzleHttp\Psr7\Response;

/**
 * Tests how hosting platform plugins handle exceptional API responses.
 *
 * @group eu_oss_catalogue
 */
class HostingPlatformFailedFetchTest extends KernelTestBase implements LoggerChannelInterface {

  use LoggerTestTrait;
  use SimpleResponseMockTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eu_oss_catalogue',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create mock client returning 500 responses by default.
    $this->setGlobalMockClientFromResponseCodes(500, 500);

    // Set a dummy access token.
    $settings = Settings::getAll();
    $settings['eu_oss_catalogue']['platform_github']['access_token'] = 'foo';
    new Settings($settings);
  }

  /**
   * Tests developers_italia hosting platform plugin.
   *
   * @param string $providerId
   *   The ID of the plugin to test. Defaults to "developers_italia".
   */
  public function testFetch(string $providerId = 'developers_italia'): void {
    $providerManager = $this->container->get('plugin.manager.eu_oss_catalogue.provider');
    $this->assertInstanceOf(EuOssCataloguePluginManagerInterface::class, $providerManager);
    $provider = $providerManager->createInstance($providerId);
    $this->assertInstanceOf(EuOssCatalogueProviderInterface::class, $provider);
    $this->expectException(HostingPlatformFetchException::class);
    iterator_to_array($provider->fetch());
  }

  /**
   * Tests hosting platform plugin derivatives of "hosting_platform".
   *
   * @param int[] $statusCodes
   *   The response status codes the mocked client must return.
   * @param string $pluginId
   *   The ID of the provider plugin to test.
   * @param array $config
   *   The configuration of the provider plugin.
   *
   * @dataProvider providerTestHostingPlatformFetch
   */
  public function testHostingPlatformFetch(array $statusCodes, string $pluginId, array $config): void {
    $this->setGlobalMockClientFromResponseCodes(...array_merge($statusCodes, [500]));

    $hostingPlatformConfigStorage = $this->container->get('entity_type.manager')->getStorage('hosting_platform');
    $this->assertInstanceOf(ConfigEntityStorageInterface::class, $hostingPlatformConfigStorage);
    $hostingPlatformConfig = $hostingPlatformConfigStorage->create([
      'id' => $pluginId,
      'label' => $pluginId,
      'platformId' => $pluginId,
      'platformSettings' => $config,
    ]);
    $hostingPlatformConfig->save();
    $hostingPlatformConfigStorage->resetCache([$hostingPlatformConfig->id()]);

    $this->testFetch("hosting_platform:$pluginId");
  }

  /**
   * Data provider for ::testHostingPlatformFetch.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestHostingPlatformFetch(): array {
    return [
      'Bitbucket.org' => [
        'Mocked errors' => [500],
        'Plugin ID' => 'bitbucket_org',
        'Config' => [
          'baseUrl' => 'https://bitbucket.com',
          'projects' => ['foo' => NULL],
        ],
      ],
      'Github.com' => [
        'Mocked errors' => [500],
        'Plugin ID' => 'github',
        'Config' => [
          'baseUrl' => 'https://github.com',
          'projects' => ['foo' => NULL],
        ],
      ],
      'Gitlab.com' => [
        'Mocked errors' => [500],
        'Plugin ID' => 'gitlab_com',
        'Config' => [
          'baseUrl' => 'https://gitlab.com',
          'projects' => ['foo' => NULL],
        ],
      ],
      'Gitlab, self-hosted, no GraphQL' => [
        'Mocked errors' => [503],
        'Plugin ID' => 'gitlab_self_hosted',
        'Config' => [
          'baseUrl' => 'https://gitlab.example.com',
          'projects' => ['foo' => NULL],
        ],
      ],
      'Gitlab, self-hosted, not a group' => [
        'Mocked errors' => [404, 503],
        'Plugin ID' => 'gitlab_self_hosted',
        'Config' => [
          'baseUrl' => 'https://gitlab.example.com',
          'projects' => ['foo' => ['bar']],
        ],
      ],
      'Gitlab, self-hosted, everything' => [
        'Mocked errors' => [429, 429],
        'Plugin ID' => 'gitlab_self_hosted',
        'Config' => [
          'baseUrl' => 'https://gitlab.example.com',
          'projects' => [],
        ],
      ],
    ];
  }

  /**
   * Verifies that cleanup task isn't queued if provider fails to fetch.
   */
  public function testNoCleanupQueued(): void {
    // Set up logger.
    \Drupal::service('logger.factory')->addLogger($this);
    // Add time service mock.
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn(1000);
    \Drupal::getContainer()->set('datetime.time', $time->reveal());

    // Queue is empty before fetch.
    $euOssCatalogueQueue = $this->container->get('queue')->get('eu_oss_catalogue');
    $this->assertInstanceOf(QueueInterface::class, $euOssCatalogueQueue);
    $this->assertSame(0, $euOssCatalogueQueue->numberOfItems());

    // Fetch Developers Italia. Mock returns 500 responses, so we expect no
    // parsed solutions.
    $euOssCatalogue = $this->container->get('eu_oss_catalogue');
    $this->assertInstanceOf(EuOssCatalogueInterface::class, $euOssCatalogue);
    $euOssCatalogue->fetch('developers_italia');

    // Queue must be still empty.
    $this->assertSame(0, $euOssCatalogueQueue->numberOfItems());
    $this->assertEquals(
      [
        [
          'message' => "Unstable API behind '@provider' provider plugin, skipping cleanup. Exceptions:\n@exception-messages",
          'context' => [
            '@provider' => 'developers_italia',
            '@exception-messages' => "Server error: `GET https://api.developers.italia.it/v1/software?page%5Bsize%5D=100&all=false` resulted in a `500 Internal Server Error` response:\nInternal server error\n",
            'channel' => 'eu_oss_catalogue',
            'link' => '',
            'uid' => 0,
            'request_uri' => 'http://localhost/',
            'referer' => '',
            'ip' => '127.0.0.1',
            'timestamp' => 1000,
          ],
        ],
      ],
      $this->log[RfcLogLevel::ERROR],
    );
  }

  /**
   * Sets a mock HTTP client which returns responses having the given status.
   *
   * @param int ...$responseCodes
   *   The response codes.
   */
  protected function setGlobalMockClientFromResponseCodes(int ...$responseCodes): void {
    $responses = [];
    foreach ($responseCodes as $statusCode) {
      $message = match($statusCode) {
        500 => 'Internal server error',
        503 => 'Temporarily unavailable',
        429 => 'Too many requests',
        default => NULL,
      };
      $responses[] = new Response(
        $statusCode,
        ['Content-Type' => 'text/html'],
        $message
      );
    }
    $this->setGlobalMockClient(...$responses);
  }

}
