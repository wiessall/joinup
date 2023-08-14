<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\eu_oss_catalogue\ApiGuesser;
use Drupal\eu_oss_catalogue_test\Plugin\ServiceMock\BitbucketServiceMock;
use Drupal\eu_oss_catalogue_test\Plugin\ServiceMock\GitlabServiceMock;
use Drupal\KernelTests\KernelTestBase;

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
    'http_request_mock',
  ];

  protected ApiGuesser $apiGuesser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->apiGuesser = new ApiGuesser($this->container->get('http_client_factory'));

    $this->container->get('state')->set(
      GitlabServiceMock::GITLAB_MOCK_STATE_KEY,
      [
        'git.fpfis.tech.ec.europa.eu/path' => [
          'ec-europa' => [
            'digit-joinup-reference'
          ],
        ],
      ]
    );

    $this->container->get('state')->set(
      BitbucketServiceMock::BITBUCKET_MOCK_STATE_KEY,
      [
        'bitbucket.example.com' => [
          'atlassianlabs' => [
            'docker-browse'
          ],
        ],
      ]
    );
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
    $this->assertEquals(
      $this->apiGuesser->findApiPlugin($url),
      $expected_api
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
      'Gitlab home' => [
        'URL' => 'https://git.fpfis.tech.ec.europa.eu/path/ec-europa/digit-joinup-reference',
        'Expected' => 'gitlab',
      ],
      'Gitlab HTTP URL' => [
        'URL' => 'https://git.fpfis.tech.ec.europa.eu/path/ec-europa/digit-joinup-reference.git',
        'Expected' => 'gitlab',
      ],
      'Gitlab SSH URL' => [
        'URL' => 'git@git.fpfis.tech.ec.europa.eu/path:ec-europa/digit-joinup-reference.git',
        'Expected' => 'gitlab',
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

      'Bitbucket home' => [
        'URL' => 'https://bitbucket.example.com/atlassianlabs/docker-browse',
        'Expected' => 'bitbucket',
      ],
      'Bitbucket HTTP URL' => [
        'URL' => 'https://bitbucket.example.com/atlassianlabs/docker-browse.git',
        'Expected' => 'bitbucket',
      ],
      'Bitbucket SSH URL' => [
        'URL' => 'git@bitbucket.example.com:atlassianlabs/docker-browse.git',
        'Expected' => 'bitbucket',
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
    $this->apiGuesser->findApiPlugin($url);
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
