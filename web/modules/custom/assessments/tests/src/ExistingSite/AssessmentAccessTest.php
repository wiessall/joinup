<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\Entity\Assessment;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Tests the assessment access control handler.
 *
 * This test suite verifies that the assessment entity access control works
 * correctly across different user roles and assessment states. It tests view,
 * create, update, and delete operations for various user personas.
 *
 * @group assessments
 * @coversDefaultClass \Drupal\assessments\AssessmentAccessControlHandler
 */
class AssessmentAccessTest extends JoinupExistingSiteTestBase {

  /**
   * The assessment access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $accessHandler;

  /**
   * Authenticated user (has authenticated role with default permissions).
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $authenticatedUser;

  /**
   * Authenticated user who owns the test assessments.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $ownerUser;

  /**
   * Authenticated user without ownership of test assessments.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $nonOwnerUser;

  /**
   * User with moderator role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $moderatorUser;

  /**
   * User with assessment_reporter role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $assessmentReporterUser;

  /**
   * User with assessment_moderator role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $assessmentModeratorUser;

  /**
   * Test assessments in various workflow states.
   *
   * @var \Drupal\assessments\Entity\Assessment[]
   */
  protected array $testAssessments = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->accessHandler = \Drupal::entityTypeManager()
      ->getAccessControlHandler('assessment');

    $this->createTestUsers();
    $this->createTestAssessments();
  }

  /**
   * Creates test users with various roles and permissions.
   */
  protected function createTestUsers(): void {
    $this->authenticatedUser = $this->createUser();
    $this->ownerUser = $this->createUser();
    $this->nonOwnerUser = $this->createUser();
    $this->moderatorUser = $this->createUser([], NULL, FALSE, ['roles' => ['moderator']]);
    $this->assessmentReporterUser = $this->createUser([], NULL, FALSE, ['roles' => ['assessment_reporter']]);
    $this->assessmentModeratorUser = $this->createUser([], NULL, FALSE, ['roles' => ['assessment_moderator']]);
  }

  /**
   * Creates test assessments in all workflow states.
   */
  protected function createTestAssessments(): void {
    foreach (['draft', 'review', 'published', 'require_updates'] as $state) {
      $this->testAssessments[$state] = Assessment::create([
        'type' => 'report',
        'name' => ucfirst($state) . ' Assessment Report',
        'state' => $state,
        'uid' => $this->ownerUser->id(),
      ]);
      $this->testAssessments[$state]->save();
      $this->markEntityForCleanup($this->testAssessments[$state]);
    }
  }

  /**
   * Tests view access for published assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkViewAccess
   */
  public function testViewPublishedAssessment(): void {
    $assessment = $this->testAssessments['published'];

    $this->assertTrue($assessment->access('view', $this->ownerUser));
    $this->assertTrue($assessment->access('view', $this->moderatorUser));
    $this->assertTrue($assessment->access('view', $this->assessmentReporterUser));
    $this->assertTrue($assessment->access('view', $this->assessmentModeratorUser));
    $this->assertTrue($assessment->access('view', $this->authenticatedUser));
    $this->assertTrue($assessment->access('view', User::getAnonymousUser()));
  }

  /**
   * Tests view access for non-published assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkViewAccess
   * @dataProvider nonPublishedStatesProvider
   */
  public function testViewNonPublishedAssessment(string $state): void {
    $assessment = $this->testAssessments[$state];

    $this->assertTrue($assessment->access('view', $this->ownerUser));
    $this->assertTrue($assessment->access('view', $this->moderatorUser));
    $this->assertTrue($assessment->access('view', $this->assessmentModeratorUser));

    $this->assertFalse($assessment->access('view', $this->assessmentReporterUser));
    $this->assertFalse($assessment->access('view', $this->nonOwnerUser));
    $this->assertFalse($assessment->access('view', $this->authenticatedUser));
  }

  /**
   * Tests create access for assessment reports.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateAssessment(): void {
    $this->assertTrue($this->accessHandler->createAccess('report', $this->ownerUser));
    $this->assertTrue($this->accessHandler->createAccess('report', $this->assessmentReporterUser));
    $this->assertTrue($this->accessHandler->createAccess('report', $this->assessmentModeratorUser));
    $this->assertTrue($this->accessHandler->createAccess('report', $this->moderatorUser));
    $this->assertTrue($this->accessHandler->createAccess('report', $this->authenticatedUser));

    $this->assertFalse($this->accessHandler->createAccess('report', User::getAnonymousUser()));
  }

  /**
   * Tests update access for draft assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkUpdateAccess
   */
  public function testUpdateDraftAssessment(): void {
    $assessment = $this->testAssessments['draft'];

    $this->assertTrue($assessment->access('update', $this->ownerUser));

    $this->assertTrue($assessment->access('update', $this->moderatorUser));
    $this->assertFalse($assessment->access('update', $this->assessmentModeratorUser));
    $this->assertFalse($assessment->access('update', $this->assessmentReporterUser));
    $this->assertFalse($assessment->access('update', $this->nonOwnerUser));
    $this->assertFalse($assessment->access('update', $this->authenticatedUser));
  }

  /**
   * Tests update access for review state assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkUpdateAccess
   */
  public function testUpdateReviewAssessment(): void {
    $assessment = $this->testAssessments['review'];

    $this->assertFalse($assessment->access('update', $this->ownerUser));
    $this->assertTrue($assessment->access('update', $this->moderatorUser));
    $this->assertFalse($assessment->access('update', $this->assessmentReporterUser));
    $this->assertFalse($assessment->access('update', $this->assessmentModeratorUser));
    $this->assertFalse($assessment->access('update', $this->nonOwnerUser));
    $this->assertFalse($assessment->access('update', $this->authenticatedUser));
  }

  /**
   * Tests access to the custom moderator form for assessment reports.
   */
  public function testModerationFormAccess(): void {
    $review_assessment = $this->testAssessments['review'];

    $this->assertTrue(_assessments_can_moderate($review_assessment, $this->assessmentModeratorUser)->isAllowed());
    $this->assertFalse(_assessments_can_moderate($review_assessment, $this->ownerUser)->isAllowed());
    $this->assertFalse(_assessments_can_moderate($review_assessment, $this->moderatorUser)->isAllowed());
    $this->assertFalse(_assessments_can_moderate($review_assessment, $this->assessmentReporterUser)->isAllowed());
    $this->assertFalse(_assessments_can_moderate($review_assessment, $this->authenticatedUser)->isAllowed());

    foreach (['draft', 'published', 'require_updates'] as $state) {
      $this->assertFalse(
        _assessments_can_moderate($this->testAssessments[$state], $this->assessmentModeratorUser)->isAllowed(),
        sprintf('Assessment moderator should not have access to the moderation form for %s state.', $state)
      );
    }
  }

  /**
   * Tests update access for published state assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkUpdateAccess
   */
  public function testUpdatePublishedAssessment(): void {
    $assessment = $this->testAssessments['published'];

    $this->assertTrue($assessment->access('update', $this->moderatorUser));

    $this->assertFalse($assessment->access('update', $this->ownerUser));
    $this->assertFalse($assessment->access('update', $this->assessmentReporterUser));
    $this->assertFalse($assessment->access('update', $this->assessmentModeratorUser));
    $this->assertFalse($assessment->access('update', $this->nonOwnerUser));
    $this->assertFalse($assessment->access('update', $this->authenticatedUser));
  }

  /**
   * Tests delete access for assessment reports in all states.
   *
   * @covers ::checkAccess
   * @covers ::checkDeleteAccess
   * @dataProvider allStatesProvider
   */
  public function testDeleteAssessment(string $state): void {
    $assessment = $this->testAssessments[$state];

    $this->assertTrue($assessment->access('delete', $this->moderatorUser));

    $this->assertFalse($assessment->access('delete', $this->assessmentReporterUser));
    $this->assertFalse($assessment->access('delete', $this->assessmentModeratorUser));
    $this->assertFalse($assessment->access('delete', $this->ownerUser));
    $this->assertFalse($assessment->access('delete', $this->nonOwnerUser));
    $this->assertFalse($assessment->access('delete', $this->authenticatedUser));
  }

  /**
   * Tests view access for public_organisation assessments.
   *
   * @covers ::checkAccessForPublicOrganisation
   */
  public function testPublicOrganisationAccess(): void {
    $access_handler = \Drupal::entityTypeManager()
      ->getAccessControlHandler('assessment');
    $organisation = Assessment::create([
      'type' => 'public_organisation',
      'name' => 'Test Organisation',
      'status' => TRUE,
      'uid' => $this->ownerUser->id(),
    ]);
    $organisation->save();
    $this->markEntityForCleanup($organisation);

    // Test access without related report.
    $access_handler->resetCache();
    $this->assertFalse($organisation->access('view', $this->ownerUser));
    $this->assertTrue($organisation->access('view', $this->moderatorUser));
    $this->assertFalse($organisation->access('view', $this->assessmentReporterUser));
    $this->assertFalse($organisation->access('view', $this->assessmentModeratorUser));
    $this->assertFalse($organisation->access('view', $this->authenticatedUser));
    $this->assertFalse($organisation->access('view', User::getAnonymousUser()));

    /** @var \Drupal\assessments\Entity\AssessmentReport $report */
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Test Assessment Report',
      'state' => 'draft',
      'uid' => $this->ownerUser->id(),
      'provider' => $organisation->id(),
    ]);
    $report->save();
    $this->markEntityForCleanup($report);

    // Test access with related report as draft.
    $access_handler->resetCache();
    $this->assertTrue($organisation->access('view', $this->ownerUser));
    $this->assertTrue($organisation->access('view', $this->moderatorUser));
    $this->assertTrue($organisation->access('view', $this->assessmentModeratorUser));
    $this->assertFalse($organisation->access('view', $this->authenticatedUser));
    $this->assertFalse($organisation->access('view', $this->assessmentReporterUser));
    $this->assertFalse($organisation->access('view', User::getAnonymousUser()));

    // Test access with related report as published.
    $report->setWorkflowState('published')->save();
    $access_handler->resetCache();
    $this->assertTrue($organisation->access('view', $this->ownerUser));
    $this->assertTrue($organisation->access('view', $this->moderatorUser));
    $this->assertTrue($organisation->access('view', $this->assessmentReporterUser));
    $this->assertTrue($organisation->access('view', $this->assessmentModeratorUser));
    $this->assertTrue($organisation->access('view', $this->authenticatedUser));
    $this->assertTrue($organisation->access('view', User::getAnonymousUser()));
  }

  /**
   * Data provider for non-published assessment states.
   *
   * @return array
   *   Test cases with state names.
   */
  public function nonPublishedStatesProvider(): array {
    return [
      'draft' => ['draft'],
      'review' => ['review'],
      'require_updates' => ['require_updates'],
    ];
  }

  /**
   * Data provider for all assessment states.
   *
   * @return array
   *   Test cases with state names.
   */
  public function allStatesProvider(): array {
    return [
      'draft' => ['draft'],
      'review' => ['review'],
      'published' => ['published'],
      'require_updates' => ['require_updates'],
    ];
  }

  /**
   * Creates a user with a specific role.
   *
   * @param string $role_id
   *   The role ID to assign.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  protected function createUserWithRole(string $role_id): UserInterface {
    $user = $this->createUser();
    $user->addRole($role_id);
    $user->save();

    return $user;
  }

  /**
   * Tests getRelatedReport for report entities returns NULL.
   *
   * @covers \Drupal\assessments\Entity\Assessment::getRelatedReport
   */
  public function testGetRelatedReportForReportReturnsNull(): void {
    $report = $this->testAssessments['published'];

    $this->assertNull(
      $report->getRelatedReport(),
      'getRelatedReport() should return NULL for report entities.'
    );
  }

  /**
   * Tests getRelatedReport for PublicOrganisation with published report.
   *
   * @covers \Drupal\assessments\Entity\PublicOrganisation::getRelatedReport
   */
  public function testGetRelatedReportForPublicOrganisationWithPublishedReport(): void {
    // Create a public organisation.
    $organisation = Assessment::create([
      'type' => 'public_organisation',
      'name' => 'Test Organisation',
      'status' => TRUE,
    ]);
    $organisation->save();
    $this->markEntityForCleanup($organisation);

    // Use existing published report and link it to the organisation.
    $published_report = $this->testAssessments['published'];
    $published_report->set('provider', $organisation->id());
    $published_report->save();

    // Test that getRelatedReport returns the published report.
    $related_report = $organisation->getRelatedReport();
    $this->assertNotNull($related_report, 'getRelatedReport() should return a report.');
    $this->assertEquals(
      $published_report->id(),
      $related_report->id(),
      'getRelatedReport() should return the published report.'
    );
  }

  /**
   * Tests getRelatedReport prioritizes published over unpublished reports.
   *
   * @covers \Drupal\assessments\Entity\PublicOrganisation::getRelatedReport
   */
  public function testGetRelatedReportPrioritizesPublishedReports(): void {
    // Create a public organisation.
    $organisation = Assessment::create([
      'type' => 'public_organisation',
      'name' => 'Test Organisation',
      'status' => TRUE,
    ]);
    $organisation->save();
    $this->markEntityForCleanup($organisation);

    // Use existing unpublished report and link it to the organisation.
    $unpublished_report = $this->testAssessments['draft'];
    $unpublished_report->set('provider', $organisation->id());
    $unpublished_report->save();

    // Use existing published report and link it to the organisation.
    $published_report = $this->testAssessments['published'];
    $published_report->set('provider', $organisation->id());
    $published_report->save();

    // Test that getRelatedReport returns
    // the published report (not unpublished).
    $related_report = $organisation->getRelatedReport();
    $this->assertNotNull($related_report, 'getRelatedReport() should return a report.');
    $this->assertEquals(
      $published_report->id(),
      $related_report->id(),
      'getRelatedReport() should prioritize published reports over unpublished ones.'
    );
    $this->assertTrue(
      $related_report->isPublished(),
      'The related report should be published.'
    );
  }

  /**
   * Tests getRelatedReport for sub-entities with paragraph references.
   *
   * @covers \Drupal\assessments\Entity\AssessmentSubEntity::getRelatedReport
   * @dataProvider subEntityTypesProvider
   */
  public function testGetRelatedReportForSubEntities(string $bundle, string $paragraph_field): void {
    // Use existing published report.
    $report = $this->testAssessments['published'];

    // Create a sub-entity.
    $sub_entity = Assessment::create([
      'type' => $bundle,
      'name' => 'Test ' . ucfirst($bundle),
      'status' => TRUE,
    ]);
    $sub_entity->save();
    $this->markEntityForCleanup($sub_entity);

    // Create a paragraph that references the sub-entity.
    $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->create([
      'type' => 'binding_requirement',
      $paragraph_field => $sub_entity->id(),
      'parent_id' => $report->id(),
      'parent_type' => 'assessment',
      'parent_field_name' => 'binding_requirement',
    ]);
    $paragraph->save();
    $this->markEntityForCleanup($paragraph);

    // Test that getRelatedReport returns the parent report.
    $related_report = $sub_entity->getRelatedReport();
    $this->assertNotNull(
      $related_report,
      sprintf('getRelatedReport() should return a report for %s entities.', $bundle)
    );
    $this->assertEquals(
      $report->id(),
      $related_report->id(),
      sprintf('getRelatedReport() should return the correct parent report for %s.', $bundle)
    );
  }

  /**
   * Data provider for sub-entity types and their paragraph fields.
   *
   * @return array
   *   Test cases with bundle names and paragraph field names.
   */
  public function subEntityTypesProvider(): array {
    return [
      'expression' => ['expression', 'expression'],
      'participation' => ['participation', 'has_participation'],
      'public_service' => ['public_service', 'affects'],
    ];
  }

}
