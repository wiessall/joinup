<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform\GitHub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Client\ClientInterface;

/**
 * Tests computing file raw URL.
 */
#[CoversClass(GitHub::class)]
#[Group('eu_oss_catalogue')]
class FileRawUrlTest extends UnitTestCase {

  /**
   * Tests computing the raw URL of a file hosted on a coding platform.
   *
   * @param string $class
   *   The coding hosting platform plugin class.
   * @param string $platformUrl
   *   The base URL of the coding hosting platform.
   * @param string $url
   *   The URL to be checked.
   * @param string $projectUrl
   *   The project URL.
   * @param string|null $expectedUrl
   *   The expected URL.
   */
  #[DataProvider('providerFileRawUrl')]
  public function testFileRawUrl(
    string $class,
    string $platformUrl,
    string $url,
    string $projectUrl,
    ?string $expectedUrl,
  ): void {
    $httpClient = $this->prophesize(ClientInterface::class)->reveal();
    $logger = $this->prophesize(LoggerChannelInterface::class)->reveal();
    $cacheBackend = $this->prophesize(CacheBackendInterface::class)->reveal();
    $settings = new Settings([]);
    $definition = ['baseUrl' => $platformUrl];
    $plugin = new $class(
      [],
      '',
      $definition,
      $httpClient,
      $settings,
      $logger,
      $cacheBackend,
    );
    $actualUrl = $plugin->getFileRawUrl($url, $projectUrl);
    $this->assertSame($expectedUrl, $actualUrl);
  }

  /**
   * Provides test cases for ::testFileRawUrl().
   *
   * @return array
   *   A list of testing cases. For the structure of each array element please
   *   check ::testFileRawUrl() signature.
   */
  public static function providerFileRawUrl(): array {
    return Yaml::decode(file_get_contents(__DIR__ . '/../../fixtures/expectancy/file_raw_url.yml'));
  }

}
