<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentVocabularyHelper;
use Drupal\assessments\Entity\Assessment;
use Drupal\joinup_solution\Entity\Solution;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Tests the assessment workflow and related entity publication.
 *
 * @group assessments
 */
class AssessmentWorkflowTest extends JoinupExistingSiteTestBase {

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

    $this->assessmentVocabularyHelper = $this->container->get(AssessmentVocabularyHelper::class);
    $this->entityRepository = $this->container->get('entity.repository');
  }

  /**
   * Tests that publishing a report also publishes all related entities.
   *
   * @covers \Drupal\assessments\Entity\AssessmentReport::postSave
   */
  public function testReportPublishingPublishesRelatedEntities(): void {
    $bindingRequirement = $this->createParagraphBindingRequirement();
    $organisation = $this->createAssessmentOrganisation();

    /** @var \Drupal\assessments\Entity\AssessmentReport $report */
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Assessment Workflow Test Report',
      'state' => 'review',
      'binding_requirement' => ['entity' => $bindingRequirement],
      'provider' => ['entity' => $organisation],
      'status' => FALSE,
    ]);
    $this->markEntityForCleanup($report);
    $report->save();

    // Load the binding requirement paragraph to get the sub-entities.
    $bindingRequirement = $report->get('binding_requirement')->entity;
    \assert($bindingRequirement instanceof ParagraphInterface);

    $expression = $bindingRequirement->get('expression')->entity;
    $publicService = $bindingRequirement->get('affects')->entity;
    $participation = $bindingRequirement->get('has_participation')->entity;
    $asset = $bindingRequirement->get('asset')->entity;

    // Assert that the report and all related entities are unpublished.
    $this->assertFalse($report->isPublished(), 'Report should be unpublished initially.');
    $this->assertFalse($expression->isPublished(), 'Expression should be unpublished initially.');
    $this->assertFalse($publicService->isPublished(), 'Public service should be unpublished initially.');
    $this->assertFalse($participation->isPublished(), 'Participation should be unpublished initially.');
    $this->assertFalse($organisation->isPublished(), 'Organisation should be unpublished initially.');
    $this->assertTrue($asset->isPublished(), 'Assets are published by default.');

    // Publish the report by setting its state to 'published'.
    $report->setWorkflowState('published');
    $report->set('status', TRUE);
    $report->save();

    $bindingRequirement = $report->get('binding_requirement')->entity;
    \assert($bindingRequirement instanceof ParagraphInterface);

    $expression = $bindingRequirement->get('expression')->entity;
    $publicService = $bindingRequirement->get('affects')->entity;
    $participation = $bindingRequirement->get('has_participation')->entity;
    $organisation = $report->get('provider')->entity;
    $asset = $bindingRequirement->get('asset')->entity;

    // Assert that the report is published.
    $this->assertTrue($report->isPublished(), 'Report should be published after setting state to published.');
    $this->assertEquals('published', $report->getWorkflowState(), 'Report workflow state should be published.');

    // Assert that all related entities are now also published.
    $this->assertTrue($expression->isPublished(), 'Expression should be published after report is published.');
    $this->assertTrue($publicService->isPublished(), 'Public service should be published after report is published.');
    $this->assertTrue($participation->isPublished(), 'Participation should be published after report is published.');
    $this->assertTrue($organisation->isPublished(), 'Organisation should be published after report is published.');
    $this->assertTrue($asset->isPublished(), 'Asset should be published after report is published.');

    // Create a second report that reuses the provider and expression asset.
    $bindingRequirement2 = Paragraph::create([
      'type' => 'binding_requirement',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
      'expression' => ['target_id' => $expression->id()],
      'affects' => ['entity' => $publicService],
      'has_participation' => ['entity' => $participation],
    ]);
    $this->markEntityForCleanup($bindingRequirement2);

    /** @var \Drupal\assessments\Entity\AssessmentReport $report2 */
    $report2 = Assessment::create([
      'type' => 'report',
      'name' => 'Second Assessment Workflow Test Report',
      'state' => 'review',
      'binding_requirement' => ['entity' => $bindingRequirement2],
      'provider' => ['target_id' => $organisation->id()],
      'status' => FALSE,
    ]);
    $this->markEntityForCleanup($report2);
    $report2->save();

    // Reload organisation and expression to get fresh state.
    $organisation = $report2->get('provider')->entity;
    $bindingRequirement2 = $report2->get('binding_requirement')->entity;
    $expression = $bindingRequirement2->get('expression')->entity;

    // Assert that organisation and expression remain published.
    $this->assertTrue($organisation->isPublished(), 'Organisation should remain published despite being referenced by unpublished report.');
    $this->assertTrue($expression->isPublished(), 'Expression should remain published despite being referenced by unpublished report.');
    $this->assertTrue($asset->isPublished(), 'Asset should remain published despite being referenced by unpublished report.');
  }

  /**
   * Creates a binding requirement paragraph with all dependencies.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created binding requirement paragraph.
   */
  private function createParagraphBindingRequirement(): ParagraphInterface {
    $expression = Assessment::create([
      'type' => 'expression',
      'description' => ['value' => $this->randomString()],
      'documented_in' => ['uri' => 'https://interoperable-europe.ec.europa.eu/'],
    ]);
    $this->markEntityForCleanup($expression);

    $publicService = Assessment::create([
      'type' => 'public_service',
      'thematic_area' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_public_service_area', 'core_governance_and_administration')],
    ]);
    $this->markEntityForCleanup($publicService);

    $participation = Assessment::create([
      'type' => 'participation',
      'participant_type' => ['entity' => $this->assessmentVocabularyHelper->getTermFromIdentifier('assess_participant_type', 'european_union_agency')],
    ]);
    $this->markEntityForCleanup($participation);

    $collection = $this->entityRepository->loadEntityByUuid('node', AssessmentInterface::SOLUTION_COLLECTOR);
    $asset = Solution::create([
      'type' => 'solution',
      'title' => 'Test Asset',
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => ['target_id' => $collection->id()],
      // Solutions are published by default.
      // @see \assessments_inline_entity_form_entity_form_alter
      'field_state' => 'published',
    ]);
    $this->markEntityForCleanup($asset);

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
   * Creates an assessment organisation.
   *
   * @return \Drupal\assessments\AssessmentInterface
   *   The created assessment organisation.
   */
  private function createAssessmentOrganisation(): AssessmentInterface {
    $organisation = Assessment::create([
      'type' => 'public_organisation',
      'name' => 'Test Organisation',
    ]);
    $this->markEntityForCleanup($organisation);
    return $organisation;
  }

}
