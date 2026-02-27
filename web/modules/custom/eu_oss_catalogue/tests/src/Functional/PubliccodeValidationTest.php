<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * Tests validation of publiccode.yml.
 *
 * @group eu_oss_catalogue
 */
class PubliccodeValidationTest extends FetchTestBase {

  /**
   * A solution is not created when the validator API is unavailable (5xx).
   */
  public function testCreateSkippedForValidatorApiException(): void {
    $this->fetchSolutions('api_exception_provider', 1);

    $this->assertFalse(
      $this->getNodeByTitle('Project 1.1'),
      'No node should be created when the validator API is unavailable.',
    );
    $this->assertNotEmpty(
      $this->getLogEntries('Validation API unavailable for "@project-name" (@project-url) of "@provider" source/catalogue. Skipping. Error: @error'),
      'An error should be logged when the validator API returns a server error.',
    );
  }

  /**
   * A solution rejected as invalid is created once the YAML becomes valid.
   */
  public function testUpdateToValid(): void {
    $this->fetchSolutions('yaml_become_valid_provider', 2);

    $this->assertFalse(
      $this->getNodeByTitle('Project 1.1'),
      'No node should be created when the validator API returns invalid.',
    );
    $this->assertNotEmpty(
      $this->getLogEntries('Error when validating data for solution "@project-name" (@project-url) of "@provider" source/catalogue. Errors: @errors'),
      'An error should be logged when the validator API rejects a new solution.',
    );

    $this->fetchSolutions('yaml_become_valid_provider', 1);

    $createdNode = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertNotFalse($createdNode, 'Node should be created once YAML becomes valid.');
    $this->assertSame('0.2',
      $createdNode->get('oss_publiccode_yml_version')->getString()
    );
    $this->assertSame('stable',
      $createdNode->get('oss_development_status')->getString()
    );
    $this->assertSame('de',
      $createdNode->get('oss_available_languages')->getString()
    );
  }

  /**
   * A new solution is not created when the validator API returns invalid.
   */
  public function testCreateRejectedForApiInvalidPublicCode(): void {
    $this->fetchSolutions('api_invalid_new_provider', 1);

    $this->assertFalse(
      $this->getNodeByTitle('Project 1.1'),
      'No node should be created when the validator API returns invalid.',
    );
    $this->assertNotEmpty(
      $this->getLogEntries('Error when validating data for solution "@project-name" (@project-url) of "@provider" source/catalogue. Errors: @errors'),
      'An error should be logged when the validator API rejects a new solution.',
    );
  }

  /**
   * An existing solution is not updated when the validator API returns invalid.
   */
  public function testUpdateSkippedForApiInvalidPublicCode(): void {
    $this->fetchSolutions('api_invalid_update_provider', 2);

    $createdNode = $this->getNodeByTitle('Project 1.1');
    $this->assertNotFalse($createdNode, 'Node should be created on the first valid fetch.');
    $this->assertSame('0.2', $createdNode->get('oss_publiccode_yml_version')->getString());

    $this->fetchSolutions('api_invalid_update_provider', 1);

    // The node still exists with its original title — no parsing ran, so the
    // title was never replaced with the URL-based fallback.
    $existingNode = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertNotFalse($existingNode, 'Node should still exist after a skipped update.');
    $this->assertSame('0.2', $existingNode->get('oss_publiccode_yml_version')->getString());
    $this->assertSame('stable', $existingNode->get('oss_development_status')->getString());
    $this->assertSame('de', $existingNode->get('oss_available_languages')->getString());
    $this->assertStringContainsString('Update skipped: invalid publiccode.yml', $existingNode->getRevisionLogMessage());
    $this->assertNotEmpty(
      $this->getLogEntries('Error when validating data for solution "@project-name" (@project-url) of "@provider" source/catalogue. Errors: @errors'),
      'An error should be logged when the update is skipped due to API-invalid YAML.',
    );
  }

}

