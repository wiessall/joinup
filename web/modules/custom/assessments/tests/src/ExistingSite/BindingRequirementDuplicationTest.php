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
 *
 * Covers \Drupal\assessments\Hook\AssessmentsEntityHooks::paragraphDuplicate.
 */
#[Group('assessments')]
class BindingRequirementDuplicationTest extends JoinupExistingSiteTestBase {

  use AssessmentEntityCreationTrait;

  /**
   * Tests that duplicating a binding_requirement paragraph copies its files.
   */
  public function testFileDuplicate(): void {
    $file_repository = $this->container->get(FileRepositoryInterface::class);
    $file = $file_repository->writeData($this->randomString(), 'temporary://' . $this->randomMachineName() . '.txt');
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

    $duplicate_file = $duplicate->get('requirement_documents')->entity;
    $this->assertInstanceOf(FileInterface::class, $duplicate_file);
    $this->markEntityForCleanup($duplicate_file);
    $this->assertNotEquals($file->id(), $duplicate_file->id());
  }

  /**
   * Tests that referenced assessments are deep-copied on duplication.
   */
  public function testAssessmentReferencesDeepCopy(): void {
    $paragraph = $this->createParagraphBindingRequirement();
    $paragraph->save();

    $original_expression = $paragraph->get('expression')->entity;
    $original_affects = $paragraph->get('affects')->entity;
    $original_participation = $paragraph->get('has_participation')->entity;

    $this->assertInstanceOf(AssessmentInterface::class, $original_expression);
    $this->assertInstanceOf(AssessmentInterface::class, $original_affects);
    $this->assertInstanceOf(AssessmentInterface::class, $original_participation);

    $duplicate = $paragraph->createDuplicate();
    $duplicate->save();
    $this->markEntityForCleanup($duplicate);

    // Each referenced assessment must be a new, independent entity.
    foreach (['expression', 'affects', 'has_participation'] as $field_name) {
      $duplicate_ref = $duplicate->get($field_name)->entity;
      $this->assertInstanceOf(AssessmentInterface::class, $duplicate_ref, "Field $field_name should reference an assessment.");
      $this->markEntityForCleanup($duplicate_ref);
      $this->assertTrue($duplicate_ref->isNew() || $duplicate_ref->id() !== $paragraph->get($field_name)->entity->id(), "Field $field_name should point to a different entity after duplication.");
    }

    // Verify that saving the duplicate persists independent entities.
    $duplicate_expression = $duplicate->get('expression')->entity;
    $duplicate_expression->save();
    $this->markEntityForCleanup($duplicate_expression);
    $this->assertNotEquals($original_expression->id(), $duplicate_expression->id());
  }

}
