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
   * Solution was created from invalid YAML, updated to valid.
   */
  public function testUpdateToValid(): void {
    $this->fetchSolutions('yaml_become_valid_provider', 2);

    $createdNode = $this->getNodeByTitle('group1/project-1-1');
    $this->assertNotFalse($createdNode);
    $this->assertTrue($createdNode->get('oss_publiccode_yml_version')
      ->isEmpty());
    $notFoundLogEntries = $this->getLogEntries(
      'Cannot decode publiccode.yml of @url. Message: @message',
      'a:2:{s:4:"@url";s:65:"https://limited.self-hosted.gitlab.example.com/group1/project-1-1";s:8:"@message";s:57:"Indentation problem at line 58 (near " name: Project 1").";}',
    );
    $this->assertCount(1, $notFoundLogEntries, 'We should have error about invalid YAML');

    $this->fetchSolutions('yaml_become_valid_provider', 1);

    $updatedNode = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertSame('0.2',
      $updatedNode->get('oss_publiccode_yml_version')->getString()
    );
    $this->assertSame('stable',
      $updatedNode->get('oss_development_status')->getString()
    );
    $this->assertSame('de',
      $updatedNode->get('oss_available_languages')->getString()
    );
  }

  /**
   * Solution was created from valid YAML, updated to invalid.
   */
  public function testUpdateToNotValid(): void {
    $this->fetchSolutions('yaml_become_invalid_provider', 2);

    $createdNode = $this->getNodeByTitle('Project 1.1');
    $this->assertNotFalse($createdNode);
    $this->assertSame(
      '0.2',
      $createdNode->get('oss_publiccode_yml_version')->getString()
    );

    $this->fetchSolutions('yaml_become_invalid_provider', 1);

    $notFoundLogEntries = $this->getLogEntries(
      'Cannot decode publiccode.yml of @url. Message: @message',
      'a:2:{s:4:"@url";s:65:"https://limited.self-hosted.gitlab.example.com/group1/project-1-1";s:8:"@message";s:57:"Indentation problem at line 58 (near " name: Project 1").";}',
    );
    $this->assertCount(1, $notFoundLogEntries, 'We should have error about invalid YAML');
    $updatedNode = $this->getNodeByTitle('group1/project-1-1', TRUE);
    // Check some field set by resolver.
    $this->assertSame('0.2',
      $updatedNode->get('oss_publiccode_yml_version')->getString()
    );
    $this->assertSame('stable',
      $updatedNode->get('oss_development_status')->getString()
    );
    $this->assertSame('de',
      $updatedNode->get('oss_available_languages')->getString()
    );
  }

}
