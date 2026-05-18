<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\assessments\Traits\AssessmentEntityCreationTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the assessment workflow and related entity publication.
 */
#[CoversClass(AssessmentReport::class)]
#[Group('assessments')]
class AssessmentWorkflowTest extends JoinupExistingSiteTestBase {

  use AssessmentEntityCreationTrait;

  /**
   * Tests that publishing a report also publishes all related entities.
   */
  public function testReportPublishingPublishesRelatedEntities(): void {
    $asset = $this->createSolutionAsset(AssessmentInterface::SOLUTION_COLLECTOR);
    $bindingRequirement = $this->createParagraphBindingRequirement($asset);
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
   * Tests that directly creating a published report publishes related entities.
   *
   * This tests the scenario where a report is created with status=TRUE from
   * the start, rather than first creating it as unpublished and then updating.
   */
  public function testDirectlyPublishingReportPublishesRelatedEntities(): void {
    $asset = $this->createSolutionAsset(AssessmentInterface::SOLUTION_COLLECTOR);
    $bindingRequirement = $this->createParagraphBindingRequirement($asset);
    $organisation = $this->createAssessmentOrganisation();

    // Verify the organisation is unpublished before we create the report.
    $this->assertFalse($organisation->isPublished(), 'Organisation should be unpublished initially.');

    // Create a report that is published directly (status=TRUE on creation).
    /** @var \Drupal\assessments\Entity\AssessmentReport $report */
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Directly Published Report',
      'state' => 'published',
      'binding_requirement' => ['entity' => $bindingRequirement],
      'provider' => ['entity' => $organisation],
      'status' => TRUE,
    ]);
    $this->markEntityForCleanup($report);
    $report->save();

    // Reload the binding requirement paragraph to get the sub-entities.
    $bindingRequirement = $report->get('binding_requirement')->entity;
    \assert($bindingRequirement instanceof ParagraphInterface);

    $expression = $bindingRequirement->get('expression')->entity;
    $publicService = $bindingRequirement->get('affects')->entity;
    $participation = $bindingRequirement->get('has_participation')->entity;
    $organisation = $report->get('provider')->entity;
    $asset = $bindingRequirement->get('asset')->entity;

    // Assert that the report is published.
    $this->assertTrue($report->isPublished(), 'Report should be published.');
    $this->assertEquals('published', $report->getWorkflowState(), 'Report workflow state should be published.');

    // Assert that all related entities are also published immediately.
    $this->assertTrue($expression->isPublished(), 'Expression should be published when report is created as published.');
    $this->assertTrue($publicService->isPublished(), 'Public service should be published when report is created as published.');
    $this->assertTrue($participation->isPublished(), 'Participation should be published when report is created as published.');
    $this->assertTrue($organisation->isPublished(), 'Organisation should be published when report is created as published.');
    $this->assertTrue($asset->isPublished(), 'Asset should be published when report is created as published.');
  }

  /**
   * Tests adding new children to an already published report.
   *
   * When a published report is updated to add new binding requirements with
   * new child entities (expression, public_service, participation), those
   * children should be published when the report is saved.
   */
  public function testAddingChildrenToPublishedReportPublishesThem(): void {
    $bindingRequirement = $this->createParagraphBindingRequirement();
    $organisation = $this->createAssessmentOrganisation();

    // Create a published report.
    /** @var \Drupal\assessments\Entity\AssessmentReport $report */
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Published Report for Child Addition Test',
      'state' => 'published',
      'binding_requirement' => ['entity' => $bindingRequirement],
      'provider' => ['entity' => $organisation],
      'status' => TRUE,
    ]);
    $this->markEntityForCleanup($report);
    $report->save();

    // Verify the report is published.
    $this->assertTrue($report->isPublished(), 'Report should be published.');

    // Create a new binding requirement and verify its children are unpublished.
    $newBindingRequirement = $this->createParagraphBindingRequirement();
    $newExpression = $newBindingRequirement->get('expression')->entity;
    $newPublicService = $newBindingRequirement->get('affects')->entity;
    $newParticipation = $newBindingRequirement->get('has_participation')->entity;

    $this->assertFalse($newExpression->isPublished(), 'New expression should be unpublished initially.');
    $this->assertFalse($newPublicService->isPublished(), 'New public service should be unpublished initially.');
    $this->assertFalse($newParticipation->isPublished(), 'New participation should be unpublished initially.');

    // Add the new binding requirement to the published report.
    $existingBindingRequirements = $report->get('binding_requirement')->getValue();
    $existingBindingRequirements[] = ['entity' => $newBindingRequirement];
    $report->set('binding_requirement', $existingBindingRequirements);
    $report->save();

    // Reload the new binding requirement to get the updated child entities.
    $report = Assessment::load($report->id());
    $bindingRequirements = $report->get('binding_requirement')->referencedEntities();
    $newBindingRequirement = end($bindingRequirements);
    \assert($newBindingRequirement instanceof ParagraphInterface);

    $newExpression = $newBindingRequirement->get('expression')->entity;
    $newPublicService = $newBindingRequirement->get('affects')->entity;
    $newParticipation = $newBindingRequirement->get('has_participation')->entity;

    // Assert that the new children are now published.
    $this->assertTrue($newExpression->isPublished(), 'New expression should be published after adding to published report.');
    $this->assertTrue($newPublicService->isPublished(), 'New public service should be published after adding to published report.');
    $this->assertTrue($newParticipation->isPublished(), 'New participation should be published after adding to published report.');
  }

  /**
   * Tests that publishing a Report creates a timestamp.
   */
  public function testPublishingReportSetsPublishedAtDate(): void {
    $user = $this->createUser([], 'testuser', TRUE);
    $this->drupalLogin($user);
    $this->markEntityForCleanup($user);

    $bindingRequirement = $this->createParagraphBindingRequirement();
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('taxonomy_term');

    $terms = $storage->loadByProperties([
      'name' => 'Belgium',
      'vid'  => 'spatial',
    ]);
    $country = reset($terms);

    $organisation_type = Term::create([
      'vid' => 'assess_provider_type',
      'name' => 'Assessment Organisation Type',
    ]);
    $this->markEntityForCleanup($organisation_type);

    $organisation = Assessment::create([
      'type' => 'public_organisation',
      'name' => 'Test Organisation',
      'organisation_type' => ['entity' => $organisation_type],
      'country' => ['entity' => $country],
    ]);
    $this->markEntityForCleanup($organisation);

    /** @var \Drupal\assessments\Entity\AssessmentReport $report */
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Assessment Workflow Test Report',
      'binding_requirement' => ['entity' => $bindingRequirement],
      'provider' => ['entity' => $organisation],
      'state' => 'review',
      'status' => FALSE,
    ]);
    $this->markEntityForCleanup($report);
    $report->save();

    $this->assertFalse($report->isPublished());
    $this->assertEmpty($report->get('published_at'));

    // Publish the report.
    $this->drupalGet("/collection/assessments/report/{$report->id()}/moderate");
    $this->getSession()->getPage()->pressButton('Publish');

    $report = Assessment::load($report->id());
    $this->assertTrue($report->isPublished());
    $this->assertNotEmpty($report->get('published_at')->value);
  }

}
