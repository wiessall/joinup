<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\eu_oss_catalogue\Traits\SimpleResponseMockTrait;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidator;
use GuzzleHttp\Psr7\Response;

/**
 * Tests PublicCodeValidator.
 *
 * @coversDefaultClass \Drupal\eu_oss_catalogue\Validator\PublicCodeValidator
 * @group eu_oss_catalogue
 */
class PublicCodeValidatorTest extends UnitTestCase {

  use SimpleResponseMockTrait;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn('http://fake-publiccode-api.example.com');

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')->willReturn($config);
  }

  /**
   * Creates a validator with a mock HTTP client returning the given responses.
   *
   * @param \GuzzleHttp\Psr7\Response ...$responses
   *   Mock API responses to return in order.
   *
   * @return \Drupal\eu_oss_catalogue\Validator\PublicCodeValidator
   *   The validator under test.
   */
  protected function createValidator(Response ...$responses): PublicCodeValidator {
    return new PublicCodeValidator(
      configFactory: $this->configFactory,
      httpClient: $this->buildHttpClientMock(...$responses),
    );
  }

  /**
   * Tests validation with valid publiccode.yml content.
   *
   * @covers ::validate
   */
  public function testValidPublicCodeYaml(): void {
    $apiResponse = json_encode([
      'valid' => TRUE,
      'normalized' => 'name: Example',
      'results' => [],
    ]);

    $result = $this->createValidator(new Response(200, [], $apiResponse))
      ->validate('name: Example');

    $this->assertTrue($result->isValid());
    $this->assertEmpty($result->getResults());
  }

  /**
   * Tests validation with invalid publiccode.yml content.
   *
   * @covers ::validate
   */
  public function testInvalidPublicCodeYaml(): void {
    $yamlContent = <<<YAML
publiccodeYmlVersion: '0.4'
invalidField: this should not be here
YAML;

    $apiResponse = json_encode([
      'valid' => FALSE,
      'normalized' => NULL,
      'results' => [
        [
          'key' => 'invalidField',
          'description' => 'Unknown key.',
          'line' => 2,
          'column' => 0,
          'type' => 'error',
        ],
      ],
    ]);

    $result = $this->createValidator(new Response(200, [], $apiResponse))
      ->validate($yamlContent);

    $this->assertFalse($result->isValid(), 'Invalid YAML should fail validation');
    $this->assertIsArray($result->getResults());
    $this->assertNotEmpty($result->getResults(), 'Invalid YAML should have errors');
  }

  /**
   * Tests validation with malformed YAML.
   *
   * @covers ::validate
   */
  public function testMalformedYaml(): void {
    $yamlContent = <<<YAML
publiccodeYmlVersion: '0.4'
name: Example
  invalid indentation:
    - this is malformed
YAML;

    $apiResponse = json_encode([
      'valid' => FALSE,
      'normalized' => NULL,
      'results' => [
        [
          'key' => 'name',
          'description' => 'YAML parse error.',
          'line' => 3,
          'column' => 2,
          'type' => 'error',
        ],
      ],
    ]);

    $result = $this->createValidator(new Response(200, [], $apiResponse))
      ->validate($yamlContent);

    $this->assertFalse($result->isValid(), 'Malformed YAML should fail validation');
    $this->assertNotEmpty($result->getResults(), 'Malformed YAML should have errors');
  }

}
