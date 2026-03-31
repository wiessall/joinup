<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the assessment entity duplication.
 */
#[Group('assessments')]
class BindingRequirementDuplicationTest extends JoinupExistingSiteTestBase {

  /**
   * Tests that duplicating a binding_requirement paragraph copies its files.
   *
   * Covers \Drupal\assessments\Hook\AssessmentsEntityHooks::paragraphDuplicate.
   */
  public function testDuplicate(): void {
    $file_repository = $this->container->get(FileRepositoryInterface::class);
    $file = $file_repository->writeData($this->randomString(), 'temporary://' . $this->randomMachineName() . '.txt');
    $this->markEntityForCleanup($file);

    $paragraph = Paragraph::create([
      'type' => 'binding_requirement',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
      'requirement_documents' => ['entity' => $file],
    ]);
    $duplicate = $paragraph->createDuplicate();

    $duplicate_file = $duplicate->get('requirement_documents')->entity;
    $this->assertInstanceOf(FileInterface::class, $duplicate_file);
    $this->markEntityForCleanup($duplicate_file);
    $this->assertNotEquals($file->id(), $duplicate_file->id());
  }

}
