<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\assessments\Traits\AssessmentEntityCreationTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\assessments\Entity\BindingRequirementParagraph;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the assessment deletion.
 */
#[CoversClass(AssessmentReport::class)]
#[CoversClass(BindingRequirementParagraph::class)]
#[Group('assessments')]
class AssessmentDeletionTest extends JoinupExistingSiteTestBase {

  use AssessmentEntityCreationTrait;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);
  }

  /**
   * Tests assessment report deletion.
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
    $this->assertEntityExists($document);

    // Replace the organisation with a new one, assert no deletion.
    $new_organisation = $this->createAssessmentOrganisation();
    $report->set('provider', ['entity' => $new_organisation]);
    $report->save();
    $this->assertEntityExists($organisation);

    // Delete the assessment report.
    $report->delete();

    // Assert that all created entities except public_organisation assessments
    // were deleted.
    $should_persist = static fn ($entity) => ($entity instanceof AssessmentInterface && $entity->bundle() === 'public_organisation') || $entity instanceof FileInterface;
    $entities = \array_filter($this->cleanupEntities, static fn ($entity) => !$should_persist($entity));
    $this->assertCount(13, $entities);
    foreach ($entities as $entity) {
      $this->assertEntityNotExists($entity);
    }

    $entities = \array_filter($this->cleanupEntities, static fn ($entity) => $should_persist($entity));
    $this->assertCount(4, $entities);
    foreach ($entities as $entity) {
      $this->assertEntityExists($entity);
      if ($entity instanceof FileInterface) {
        // Reload and assert that the file is temporary.
        $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
        $this->assertTrue($entity->isTemporary());
      }
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

    $bindingRequirement1 = $this->createParagraphBindingRequirement($collectorAsset);
    $bindingRequirement1b = $this->createParagraphBindingRequirement($nonCollectorAsset);
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

    $bindingRequirement2 = $this->createParagraphBindingRequirement($collectorAsset);
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
   * Creates an assessment statement of the given bundle.
   */
  private function createAssessmentStatement(string $bundle): AssessmentStatement {
    $statement = AssessmentStatement::create([
      'type' => $bundle,
      'interpretation' => [
        'entity' => $this->getAssessmentVocabularyHelper()
          ->getTermFromIdentifier('assess_statement_interpretation', 'positive'),
      ],
      'statement_type' => [
        'entity' => $this->getAssessmentVocabularyHelper()
          ->getTermFromIdentifier('assess_statement_type', $bundle),
      ],
      'judgement' => ['value' => $this->randomString()],
    ]);
    $this->markEntityForCleanup($statement);
    return $statement;
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
