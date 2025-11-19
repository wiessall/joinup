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
    $ffiEnabled = ini_get('ffi.enable');
    if ($ffiEnabled === 'preload') {
      throw new \RuntimeException('FFI must be enabled (not just preload) to run this test.');
    }

    $yamlContent = <<<YAML
publiccodeYmlVersion: "0"
name: Example Software
url: https://github.com/italia/publiccode-parser-php.git
applicationSuite: Example Suite
platforms:
  - web
  - linux
categories:
  - it-development
softwareVersion: "1.0.0"
developmentStatus: stable
softwareType: standalone/desktop
description:
  en:
    localisedName: Example Software
    shortDescription: >
      An example software for testing validation.
      This description is at least 150 characters long to meet the minimum requirement.
    longDescription: >
      This is a longer description of the example software.
      It spans multiple lines and provides detailed information.
      Very long description of this software, also split
      on multiple rows. You should note what the software
      is and why one should need it. This is 316 characters.
      Very long description of this software, also split
      on multiple rows. You should note what the software
      is and why one should need it. This is 474 characters.
    features:
      - Feature one
      - Feature two
legal:
  license: MIT
maintenance:
  type: community
  contacts:
    - name: John Doe
localisation:
  localisationReady: false
  availableLanguages:
    - en
YAML;

    $result = $this->validator->validate($yamlContent);

    $this->assertTrue($result->isValid(), 'Valid YAML should pass validation');
    $this->assertEmpty($result->getErrors(), 'Valid YAML should have no errors');
    $this->assertNotNull($result->getPublicCode(), 'Valid YAML should return a PublicCode object');
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
    $this->assertNull($result->getPublicCode(), 'Invalid YAML should return NULL for PublicCode');
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
    $this->assertNull($result->getPublicCode(), 'Malformed YAML should return NULL for PublicCode');
  }

}
