<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\file\Entity\File;
use Drupal\joinup_solution\Entity\Solution;
use Drupal\og\OgGroupAudienceHelperInterface;
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
   * The entity repository.
   */
  private EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);
    $this->assessmentVocabularyHelper = $this->container->get(AssessmentVocabularyHelper::class);
    $this->entityRepository = $this->container->get('entity.repository');
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
    $document = $this->createFileDocument();
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Assessment title',
      'state' => 'draft',
      'binding_requirement' => ['entity' => $binding_requirement],
      'documents' => ['entity' => $document],
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

    // Replace the binding requirement with a new one, assert deletion.
    $new_binding_requirement = $this->createParagraphBindingRequirement();
    $report->set('binding_requirement', ['entity' => $new_binding_requirement]);
    $report->save();
    $this->assertEntityNotExists($binding_requirement);

    // Replace the document with a new one, assert deletion.
    $new_document = $this->createFileDocument();
    $report->set('documents', ['entity' => $new_document]);
    $report->save();
    $this->assertEntityNotExists($document);

    // Replace the organisation with a new one, assert no deletion.
    $new_organisation = $this->createAssessmentOrganisation();
    $report->set('provider', ['entity' => $new_organisation]);
    $report->save();
    $this->assertEntityExists($organisation);

    // Delete the assessment report.
    $report->delete();

    // Assert that all created entities except public_organisation assessments
    // were deleted.
    $should_persist = static fn ($entity) => $entity instanceof AssessmentInterface && $entity->bundle() === 'public_organisation';
    $entities = \array_filter($this->cleanupEntities, static fn ($entity) => !$should_persist($entity));
    $this->assertCount(17, $entities);
    foreach ($entities as $entity) {
      $this->assertEntityNotExists($entity);
    }

    $entities = \array_filter($this->cleanupEntities, static fn ($entity) => $should_persist($entity));
    $this->assertCount(2, $entities);
    foreach ($entities as $entity) {
      $this->assertEntityExists($entity);
    }
  }

  /**
   * Tests that SOLUTION_COLLECTOR assets are deleted only when orphaned.
   *
   * Assets belonging to other groups must never be deleted on report deletion.
   *
   * @covers \Drupal\assessments\Entity\AssessmentReport::postDelete
   */
  public function testOrphanedAssetDeletion(): void {
    $collectorAsset = $this->createSolutionAsset(AssessmentInterface::SOLUTION_COLLECTOR);
    $nonCollectorAsset = $this->createSolutionAsset(AssessmentInterface::GROUP_UUID);

    $bindingRequirement1 = $this->createParagraphBindingRequirementWithAsset($collectorAsset);
    $bindingRequirement1b = $this->createParagraphBindingRequirementWithAsset($nonCollectorAsset);
    $report1 = Assessment::create([
      'type' => 'report',
      'name' => 'Orphaned asset test report 1',
      'state' => 'draft',
      'binding_requirement' => [
        ['entity' => $bindingRequirement1],
        ['entity' => $bindingRequirement1b],
      ],
    ]);
    $this->markEntityForCleanup($report1);
    $report1->save();

    $bindingRequirement2 = $this->createParagraphBindingRequirementWithAsset($collectorAsset);
    $report2 = Assessment::create([
      'type' => 'report',
      'name' => 'Orphaned asset test report 2',
      'state' => 'draft',
      'binding_requirement' => ['entity' => $bindingRequirement2],
    ]);
    $this->markEntityForCleanup($report2);
    $report2->save();

    // Collector asset is still referenced by report2; non-collector is never
    // deleted.
    $report1->delete();
    $this->assertEntityExists($collectorAsset);
    $this->assertEntityExists($nonCollectorAsset);

    // Collector asset is now orphaned and must be deleted; non-collector
    // persists.
    $report2->delete();
    $this->assertEntityNotExists($collectorAsset);
    $this->assertEntityExists($nonCollectorAsset);
  }

  /**
   * Creates a solution node inside the given collection.
   */
  private function createSolutionAsset(string $groupUuid): Solution {
    $collection = $this->entityRepository->loadEntityByUuid('node', $groupUuid);
    $asset = Solution::create([
      'type' => 'solution',
      'title' => 'Test Asset ' . $this->randomMachineName(),
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => ['target_id' => $collection->id()],
      'field_state' => 'published',
    ]);
    $this->markEntityForCleanup($asset);
    return $asset;
  }

  /**
   * Creates a binding requirement paragraph referencing the given solution.
   */
  private function createParagraphBindingRequirementWithAsset(Solution $asset): ParagraphInterface {
    $expression = Assessment::create([
      'type' => 'expression',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
    ]);
    $this->markEntityForCleanup($expression);

    $publicService = Assessment::create([
      'type' => 'public_service',
      'thematic_area' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_public_service_area', 'general_public_services')],
    ]);
    $this->markEntityForCleanup($publicService);

    $participation = Assessment::create([
      'type' => 'participation',
      'participant_type' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_participant_type', 'european_union_agency')],
    ]);
    $this->markEntityForCleanup($participation);

    $bindingRequirement = Paragraph::create([
      'type' => 'binding_requirement',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
      'expression' => ['entity' => $expression],
      'affects' => ['entity' => $publicService],
      'has_participation' => ['entity' => $participation],
      'asset' => ['entity' => $asset],
    ]);
    $this->markEntityForCleanup($bindingRequirement);

    return $bindingRequirement;
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
      'thematic_area' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_public_service_area', 'general_public_services')],
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
