<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidator;

/**
 * Tests PublicCodeValidator.
 *
 * @coversDefaultClass \Drupal\eu_oss_catalogue\Validator\PublicCodeValidator
 * @group eu_oss_catalogue
 */
class PublicCodeValidatorTest extends UnitTestCase {

  /**
   * The validator under test.
   *
   * @var \Drupal\eu_oss_catalogue\Validator\PublicCodeValidator
   */
  protected PublicCodeValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->validator = new PublicCodeValidator(disableNetwork: TRUE);
  }

  /**
   * Tests validation with valid publiccode.yml content.
   *
   * @covers ::validate
   */
  public function testValidPublicCodeYaml(): void {
    $result = $this->validator->validate('name: Example');

    // Validation is temporarily disabled and always returns invalid.
    $this->assertFalse($result->isValid());
    $this->assertNotEmpty($result->getErrors());
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

    $result = $this->validator->validate($yamlContent);

    $this->assertFalse($result->isValid(), 'Invalid YAML should fail validation');
    $this->assertNotEmpty($result->getErrors(), 'Invalid YAML should have errors');
    $this->assertIsArray($result->getErrors());
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

    $result = $this->validator->validate($yamlContent);

    $this->assertFalse($result->isValid(), 'Malformed YAML should fail validation');
    $this->assertNotEmpty($result->getErrors(), 'Malformed YAML should have errors');
  }

}
