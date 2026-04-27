<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Tests\assessments\Traits\AssessmentEntityCreationTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\AssessmentInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the binding_requirement paragraph duplication.
 */
#[Group('assessments')]
class BindingRequirementDuplicationTest extends JoinupExistingSiteTestBase {

  use AssessmentEntityCreationTrait;

  /**
   * Tests that duplicating a binding_requirement paragraph copies its files.
   */
  public function testFileDuplicate(): void {
    $fileRepository = $this->container->get(FileRepositoryInterface::class);
    $file = $fileRepository->writeData($this->randomString(), 'temporary://' . $this->randomMachineName() . '.txt');
    $this->markEntityForCleanup($file);

    $paragraph = Paragraph::create([
      'type' => 'binding_requirement',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
      'requirement_documents' => ['entity' => $file],
    ]);
    $paragraph->save();
    $this->markEntityForCleanup($paragraph);

    $duplicate = $paragraph->createDuplicate();
    $duplicate->save();
    $this->markEntityForCleanup($duplicate);

    $duplicatedFile = $duplicate->get('requirement_documents')->entity;
    $this->assertInstanceOf(FileInterface::class, $duplicatedFile);
    $this->markEntityForCleanup($duplicatedFile);
    $this->assertNotEquals($file->id(), $duplicatedFile->id());
  }

  /**
   * Tests that referenced assessments are deep-copied on duplication.
   */
  public function testAssessmentReferencesDeepCopy(): void {
    $paragraph = $this->createParagraphBindingRequirement();
    $paragraph->save();

    $originalExpression = $paragraph->get('expression')->entity;
    $originalAffects = $paragraph->get('affects')->entity;
    $originalParticipation = $paragraph->get('has_participation')->entity;

    $this->assertInstanceOf(AssessmentInterface::class, $originalExpression);
    $this->assertInstanceOf(AssessmentInterface::class, $originalAffects);
    $this->assertInstanceOf(AssessmentInterface::class, $originalParticipation);

    $duplicate = $paragraph->createDuplicate();
    $duplicate->save();
    $this->markEntityForCleanup($duplicate);

    // Each referenced assessment must be a new, independent entity.
    foreach (['expression', 'affects', 'has_participation'] as $fieldName) {
      $duplicateRef = $duplicate->get($fieldName)->entity;
      $this->assertInstanceOf(AssessmentInterface::class, $duplicateRef, "Field $fieldName should reference an assessment.");
      $this->markEntityForCleanup($duplicateRef);
      $this->assertTrue($duplicateRef->isNew() || $duplicateRef->id() !== $paragraph->get($fieldName)->entity->id(), "Field $fieldName should point to a different entity after duplication.");
    }

    // Verify that saving the duplicate persists independent entities.
    $duplicateExpression = $duplicate->get('expression')->entity;
    $duplicateExpression->save();
    $this->markEntityForCleanup($duplicateExpression);
    $this->assertNotEquals($originalExpression->id(), $duplicateExpression->id());
  }

}
