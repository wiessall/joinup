<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Tests the assessment deletion.
 *
 * @group assessments
 */
class AssessmentDeletionTest extends JoinupExistingSiteTestBase {

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The assessment vocabulary helper.
   */
  private AssessmentVocabularyHelper $assessmentVocabularyHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);
    $this->assessmentVocabularyHelper = $this->container->get(AssessmentVocabularyHelper::class);
  }

  /**
   * Tests assessment report deletion.
   *
   * @covers \Drupal\assessments\Entity\AssessmentReport::postSave
   * @covers \Drupal\assessments\Entity\AssessmentReport::postDelete
   * @covers \Drupal\assessments\Entity\BindingRequirementParagraph::postSave
   * @covers \Drupal\assessments\Entity\BindingRequirementParagraph::postDelete
   */
  public function testAssessmentEntityDelete(): void {
    // Create assessment report with all sub-entities.
    $binding_requirement = $this->createParagraphBindingRequirement();
    $organisation = $this->createAssessmentOrganisation();
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Assessment title',
      'state' => 'draft',
      'binding_requirement' => ['entity' => $binding_requirement],
      'documents' => ['entity' => $this->createFileDocument()],
      'legal' => ['entity' => $this->createAssessmentStatement('legal')],
      'organisational' => ['entity' => $this->createAssessmentStatement('organisational')],
      'semantic' => ['entity' => $this->createAssessmentStatement('semantic')],
      'technical' => ['entity' => $this->createAssessmentStatement('technical')],
      'remaining_barriers' => ['value' => $this->randomString()],
      'other_comment' => ['value' => $this->randomString()],
      'provider' => ['entity' => $organisation],
    ]);
    $this->markEntityForCleanup($report);
    $report->save();

    // Replace the binding requirement with a new one.
    $new_binding_requirement = $this->createParagraphBindingRequirement();
    $report->set('binding_requirement', ['entity' => $new_binding_requirement]);
    $report->save();

    // Verify that the old binding requirement was deleted.
    $this->assertEntityNotExists($binding_requirement);

    // Delete the assessment report.
    $report->delete();

    // Assert that all created entities except public_organisation assessments
    // were deleted.
    $should_persist = static fn ($entity) => $entity instanceof AssessmentInterface && $entity->bundle() === 'public_organisation';
    $entities = \array_filter($this->cleanupEntities, static fn ($entity) => !$should_persist($entity));
    $this->assertCount(16, $entities);
    foreach ($entities as $entity) {
      $this->assertEntityNotExists($entity);
    }

    $entities = \array_filter($this->cleanupEntities, static fn ($entity) => $should_persist($entity));
    $this->assertCount(1, $entities);
    foreach ($entities as $entity) {
      $this->assertEntityExists($entity);
    }
  }

  /**
   * Creates a binding requirement paragraph with all dependencies.
   */
  private function createParagraphBindingRequirement(): ParagraphInterface {
    $expression = Assessment::create([
      'type' => 'expression',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
    ]);
    $this->markEntityForCleanup($expression);

    $public_service = Assessment::create([
      'type' => 'public_service',
      'thematic_area' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_public_service_area', 'core_governance_and_administration')],
    ]);
    $this->markEntityForCleanup($public_service);

    $participation = Assessment::create([
      'type' => 'participation',
      'participant_type' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_participant_type', 'european_union_agency')],
    ]);
    $this->markEntityForCleanup($participation);

    $binding_requirement = Paragraph::create([
      'type' => 'binding_requirement',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
      'expression' => ['entity' => $expression],
      'affects' => ['entity' => $public_service],
      'has_participation' => ['entity' => $participation],
      'requirement_documents' => ['entity' => $this->createFileDocument()],
    ]);
    $this->markEntityForCleanup($binding_requirement);

    return $binding_requirement;
  }

  /**
   * Creates assessment statements for all statement types.
   */
  private function createAssessmentStatement(string $bundle): AssessmentStatement {
    $statement = AssessmentStatement::create([
      'type' => $bundle,
      'interpretation' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_statement_interpretation', 'positive')],
      'statement_type' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_statement_type', $bundle)],
      'judgement' => ['value' => $this->randomString()],
    ]);
    $this->markEntityForCleanup($statement);
    return $statement;
  }

  /**
   * Creates an assessment organisation.
   */
  private function createAssessmentOrganisation(): AssessmentInterface {
    $organisation = Assessment::create([
      'type' => 'public_organisation',
      'name' => 'Test Organisation',
    ]);
    $this->markEntityForCleanup($organisation);
    return $organisation;
  }

  /**
   * Creates a document file.
   */
  private function createFileDocument(): File {
    $document = File::create([
      'uri' => 'temporary://' . $this->randomMachineName() . '.txt',
    ]);
    $this->markEntityForCleanup($document);
    return $document;
  }

  /**
   * Asserts that the given entity does actually exist in storage.
   */
  protected function assertEntityExists(EntityInterface $entity): void {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $this->assertNotNull($storage->loadUnchanged($entity->id()), sprintf('%s with ID %s does not exist.', $entity->getEntityTypeId(), $entity->id()));
  }

  /**
   * Asserts that the given entity does not actually exist in storage.
   */
  protected function assertEntityNotExists(EntityInterface $entity): void {
    if ($entity->id()) {
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      $this->assertNull($storage->loadUnchanged($entity->id()), sprintf('%s with ID %s unexpectedly exists.', $entity->getEntityTypeId(), $entity->id()));
    }
  }

}
