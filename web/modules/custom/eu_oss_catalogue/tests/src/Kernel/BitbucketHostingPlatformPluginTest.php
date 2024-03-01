<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform\Bitbucket;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the Bitbucket hosting platform plugin.
 *
 * @coversDefaultClass \Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform\Bitbucket
 *
 * @see \Drupal\eu_oss_catalogue_test\Plugin\ServiceMock\Bitbucket
 */
class BitbucketHostingPlatformPluginTest extends KernelTestBase implements LoggerChannelInterface {

  use LoggerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eu_oss_catalogue',
    'eu_oss_catalogue_test',
    'http_request_mock',
  ];

  /**
   * The captured log entries.
   */
  protected array $log = [];

  /**
   * The plugin instance we use to perform the tests.
   */
  protected Bitbucket $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->plugin = new Bitbucket(
      [],
      'bitbucket_org',
      [
        'id' => 'bitbucket_org',
        'label' => 'Bitbucket (bitbucket.org)',
        'baseUrl' => 'https://bitbucket.org',
        'weight' => 0,
      ],
      $this->container->get('http_client'),
      Settings::getInstance(),
      $this,
    );
  }

  /**
   * Tests ::getProjects.
   */
  public function testGetProjects(): void {
    $this->plugin->setConfiguration(
      $this->plugin->getConfiguration() + [
        'projects' => [
          'test_namespace' => NULL,
        ],
      ]
    );
    $projects = iterator_to_array(
      $this->plugin->getProjects(['publiccodeYml' => 'publiccode.yml'])
    );
    $this->assertCount(2, $projects);
    $projectsArray = [];
    foreach ($projects as $project) {
      $this->assertInstanceOf(Project::class, $project);
      $projectsArray[$project->getFullName()] = $project->toArray();
      // Exclude 'files'.
      unset($projectsArray[$project->getFullName()]['files']);
    }

    $this->assertEquals(
      [
        'test_namespace/bar' => [
          'namespace' => 'test_namespace',
          'name' => 'bar',
          'url' => 'https://bitbucket.org/test_namespace/bar',
          'platform' => 'bitbucket_org',
          'defaultBranch' => 'master',
          'latestReleaseTag' => '2.7.1',
          'latestReleaseDate' => '2021-05-24T09:05:31+00:00',
          'platformReference' => 'a81fa4a1-7339-4a19-8ea5-e64d5eb02865',
          'description' => '',
          'logo' => NULL,
          'numberOfForks' => 5,
          'numberOfStars' => NULL,
          'numberOfIssues' => NULL,
          'numberOfPullRequests' => 2,
        ],
        'test_namespace/baz' => [
          'namespace' => 'test_namespace',
          'name' => 'baz',
          'url' => 'https://bitbucket.org/test_namespace/baz',
          'platform' => 'bitbucket_org',
          'defaultBranch' => 'master',
          'latestReleaseTag' => 'v10.2.345',
          'latestReleaseDate' => '2024-01-29T09:22:33+03:00',
          'platformReference' => 'f9883d63-17c1-44b6-8fb1-0478c7b9e557',
          'description' => 'This is the Baz Project Git description',
          'logo' => 'https://bytebucket.org/ravatar/%7Bf9883d63-17c1-44b6-8fb1-0478c7b9e557%7D?ts=453345',
          'numberOfForks' => 0,
          'numberOfStars' => NULL,
          'numberOfIssues' => NULL,
          'numberOfPullRequests' => NULL,
        ],
      ],
      $projectsArray
    );

    $this->assertSame(
      [
        'debug' => [
          [
            'message' => "Cannot fetch number of PRs of '%repository' on '%base_url'.",
            'context' => [
              '%repository' => 'test_namespace/baz',
              '%base_url' => 'https://bitbucket.org',
            ],
          ],
          [
            'message' => "Cannot find the '%filename' file in '%repository' on '%base_url' in branch / tag / commit '%reference'.",
            'context' => [
              '%filename' => 'publiccode.yml',
              '%repository' => 'test_namespace/baz',
              '%base_url' => 'https://bitbucket.org',
              '%reference' => 'master',
            ],
          ],
        ],
      ],
      $this->log
    );
  }

  /**
   * Tests ::testIsFileRawUrl.
   *
   * @param string $url
   *   The URL of the file.
   * @param string $projectUrl
   *   The URL of the project.
   * @param bool $expected
   *   The expected result.
   *
   * @covers ::isFileRawUrl
   *
   * @dataProvider providerTestIsFileRawUrl
   */
  public function testIsFileRawUrl(string $url, string $projectUrl, bool $expected): void {
    $this->assertSame(
      $expected,
      $this->plugin->isFileRawUrl($url, $projectUrl)
    );
  }

  /**
   * Data provider of ::testIsFileRawUrl.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestIsFileRawUrl(): array {
    return [
      'Bitbucket with exclamation mark' => [
        'URL' => 'https://bitbucket.org/!api/2.0/repositories/foo/bar/src/da001cdf6ce8f3fd3f/publiccode.yml',
        'project URL' => 'https://bitbucket.org/foo/bar',
        'expected' => TRUE,
      ],
      'Bitbucket without exclamation mark' => [
        'URL' => 'https://bitbucket.org/api/2.0/repositories/foo/bar/src/master/publiccode.yml',
        'project URL' => 'https://bitbucket.org/foo/bar',
        'expected' => TRUE,
      ],
      'Bitbucket API host with tag' => [
        'URL' => 'https://api.bitbucket.org/2.0/repositories/foo/bar/src/12.2.1/publiccode.yml',
        'project URL' => 'https://bitbucket.org/foo/bar',
        'expected' => TRUE,
      ],
      'Bytebucket without exclamation mark' => [
        'URL' => 'https://bytebucket.org/api/2.0/repositories/foo/bar/src/master/publiccode.yml',
        'project URL' => 'https://bitbucket.org/foo/bar',
        'expected' => TRUE,
      ],
      'Bytebucket with exclamation mark' => [
        'URL' => 'https://bytebucket.org/!api/2.0/repositories/foo/bar/src/master/publiccode.yml',
        'project URL' => 'https://bitbucket.org/foo/bar',
        'expected' => TRUE,
      ],
      'Relative URL' => [
        'URL' => 'images/logo.png',
        'project URL' => 'https://bitbucket.org/foo/bar',
        'expected' => FALSE,
      ],
      'Project avatar' => [
        'URL' => 'https://bytebucket.org/ravatar/%7Bf9883d63-17c1-44b6-8fb1-0478c7b9e557%7D?ts=453345',
        'project URL' => 'https://bitbucket.org/foo/bar',
        'expected' => TRUE,
      ],
    ];
  }

  /**
   * Tests ::getFileRawUrl.
   *
   * @param string $source
   *   The URL of the file.
   * @param string $projectUrl
   *   The URL of the project.
   * @param string|null $expected
   *   The expected URL, or NULL if the file cannot be found in the repository.
   * @param array|null $expectedLogs
   *   The expected logged messages.
   *
   * @covers ::doGetFileRawUrl
   * @covers ::getFileRawUrl
   *
   * @dataProvider providerTestGetFileRawUrl
   */
  public function testGetFileRawUrl(
    string $source,
    string $projectUrl,
    ?string $expected,
    ?array $expectedLogs = NULL
  ): void {
    $actual = $this->plugin->getFileRawUrl($source, $projectUrl);
    $this->assertEquals(
      $expected,
      $actual
    );

    if ($expectedLogs) {
      $this->assertSame($expectedLogs, $this->log);
    }
  }

  /**
   * Data provider of ::testGetFileRawUrl.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestGetFileRawUrl(): array {
    return [
      'Missing file' => [
        'source' => 'https://bitbucket.org/fooNamespace/bar-project/missing.csv',
        'project url' => 'https://bitbucket.org/fooNamespace/bar-project',
        'expected' => NULL,
        'expected log items' => [
          'notice' => [
            [
              'message' => "Cannot find the '%filename' file in '%repository' on '%base_url' in branch / tag / commit '%reference'.",
              'context' => [
                '%filename' => 'https://bitbucket.org/fooNamespace/bar-project/missing.csv',
                '%repository' => 'fooNamespace/bar-project',
                '%base_url' => 'https://bitbucket.org',
                '%reference' => 'master',
              ],
            ],
          ],
        ],
      ],
      'File in root' => [
        'source' => 'https://bitbucket.org/fooNamespace/bar-project/publiccode.yml',
        'project url' => 'https://bitbucket.org/fooNamespace/bar-project',
        'expected' => 'https://api.bitbucket.org/2.0/repositories/fooNamespace/bar-project/src/792a6afb90c17383af0a5c3f9642c2a5cb55f6fe/publiccode.yml',
      ],
      'File in subpath' => [
        'source' => 'https://bitbucket.org/fooNamespace/bar-project/images/bar_logo.png',
        'project url' => 'https://bitbucket.org/fooNamespace/bar-project',
        'expected' => 'https://api.bitbucket.org/2.0/repositories/fooNamespace/bar-project/src/792a6afb90c17383af0a5c3f9642c2a5cb55f6fe/images/bar_logo.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $this->log[$level][] = [
      'message' => $message,
      'context' => $context,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestStack(?RequestStack $requestStack = NULL) {}

  /**
   * {@inheritdoc}
   */
  public function setCurrentUser(?AccountInterface $current_user = NULL) {}

  /**
   * {@inheritdoc}
   */
  public function setLoggers(array $loggers) {}

  /**
   * {@inheritdoc}
   */
  public function addLogger(LoggerInterface $logger, $priority = 0) {}

}
