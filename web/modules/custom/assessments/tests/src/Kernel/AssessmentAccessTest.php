<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\assessments\Entity\Assessment;
use Drupal\user\UserInterface;

/**
 * Tests the assessment access control handler.
 *
 * @group assessments
 * @coversDefaultClass \Drupal\assessments\AssessmentAccessControlHandler
 */
class AssessmentAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'assessments',
    'state_machine',
    'user',
    'system',
    'field',
  ];

  /**
   * A user with only access content permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $contentAccessUser;

  /**
   * An authenticated user (reporter role).
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authenticatedUser;

  /**
   * Another authenticated user to test "own" permission checks.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $otherAuthenticatedUser;

  /**
   * A user with moderator role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $moderatorUser;

  /**
   * A user with assessment_reporter role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $assessmentReporterUser;

  /**
   * A user with administer assessment types permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * Test assessments in various states.
   *
   * @var array
   */
  protected $testAssessments = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installEntitySchema('assessment');
    $this->installConfig(['assessments', 'system', 'field', 'user']);

    // Create assessment bundles.
    $this->createAssessmentType('report', 'Report');

    // Install the state field for the report bundle.
    $this->installStateField();

    // Create test roles with their permissions.
    $this->createRole([
      'moderate assessment report',
      'bypass assessment report moderation',
      'access content',
    ], 'moderator', ucfirst(str_replace('_', ' ', 'moderator')));

    $this->createRole([
      'bypass assessment report moderation',
      'create assessment report',
    ], 'assessment_reporter', ucfirst(str_replace('_', ' ', 'assessment_reporter')));

    // Create a dummy user first to ensure test users don't get UID 1
    // (which has special bypass permissions in Drupal).
    $this->createUser([]);

    // User with only 'access content' - create custom minimal role.
    $this->contentAccessUser = $this->createUserWithRole('content_access', [
      'access content',
    ]);

    // Authenticated users have: access content, create assessment report,
    // view own assessment report, edit own assessment report.
    // Note: 'authenticated' is a special role in Drupal, so we create users
    // with these permissions instead of using the role directly.
    $this->authenticatedUser = $this->createUserWithRole('authenticated', [
      'access content',
      'create assessment report',
      'view own assessment report',
      'edit own assessment report',
    ]);
    $this->otherAuthenticatedUser = $this->createUserWithRole('authenticated', [
      'access content',
      'create assessment report',
      'view own assessment report',
      'edit own assessment report',
    ]);

    $this->moderatorUser = $this->createUserWithRole('moderator');
    $this->assessmentReporterUser = $this->createUserWithRole('assessment_reporter');
    $this->admin = $this->createUser([
      'administer assessment types',
    ]);

    // Create test assessments in all states owned by $this->authenticatedUser.
    $states = ['draft', 'review', 'published', 'require_updates'];
    foreach ($states as $state) {
      $this->testAssessments[$state] = Assessment::create([
        'type' => 'report',
        'name' => ucfirst($state) . ' Report',
        'state' => $state,
        'uid' => $this->authenticatedUser->id(),
      ]);
      $this->testAssessments[$state]->save();
    }
  }

  /**
   * Tests view access for published assessment reports.
   *
   * View in published state is allowed for "access content",
   * "view own assessment report" OR "moderate assessment report" permission.
   *
   * @covers ::checkAccess
   * @covers ::checkViewAccess
   */
  public function testViewPublished(): void {
    $assessment = $this->testAssessments['published'];

    // Users with 'view own assessment report' can view published reports.
    $this->assertTrue($assessment->access('view', $this->authenticatedUser));

    // Users with 'moderate assessment report' can view published reports.
    $this->assertTrue($assessment->access('view', $this->moderatorUser));

    // Admin can always view.
    $this->assertTrue($assessment->access('view', $this->admin));

    // User with only 'access content' can view published reports.
    $this->assertTrue($assessment->access('view', $this->contentAccessUser));

    // User without any permissions cannot view.
    $noAccessUser = $this->createUser([]);
    $this->assertFalse($assessment->access('view', $noAccessUser));
  }

  /**
   * Tests view access for draft and review assessment reports.
   *
   * View in draft/review states is allowed for:
   * - "view own assessment report" (checks current user is owner)
   * - "moderate assessment report"
   *
   * @covers ::checkAccess
   * @covers ::checkViewAccess
   */
  public function testViewDraftAndReview(): void {
    foreach (['draft', 'review'] as $state) {
      $assessment = $this->testAssessments[$state];

      // Owner with 'view own assessment report' can view.
      $this->assertTrue($assessment->access('view', $this->authenticatedUser), "Owner should view $state report");

      // Non-owner with 'view own' permission cannot view.
      $this->assertFalse($assessment->access('view', $this->otherAuthenticatedUser), "Non-owner with 'view own' should not view $state report");

      // User with 'moderate assessment report' can view any report.
      $this->assertTrue($assessment->access('view', $this->moderatorUser), "Moderator should view $state report");

      // Admin can always view.
      $this->assertTrue($assessment->access('view', $this->admin), "Admin should view $state report");

      // User with only 'access content' cannot view draft/review.
      $this->assertFalse($assessment->access('view', $this->contentAccessUser), "User with only 'access content' should not view $state report");
    }
  }

  /**
   * Tests create access for draft assessment reports.
   *
   * Create in draft state is allowed for "create assessment report" permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateDraft(): void {
    $accessHandler = \Drupal::entityTypeManager()->getAccessControlHandler('assessment');

    // Authenticated users with 'create assessment report' can create drafts.
    $this->assertTrue($accessHandler->createAccess('report', $this->authenticatedUser, ['state' => 'draft']));

    // Assessment reporter with bypass moderation can also create drafts.
    $this->assertTrue($accessHandler->createAccess('report', $this->assessmentReporterUser, ['state' => 'draft']));

    // Admin can create.
    $this->assertTrue($accessHandler->createAccess('report', $this->admin, ['state' => 'draft']));

    // User without 'create assessment report' cannot create.
    $this->assertFalse($accessHandler->createAccess('report', $this->contentAccessUser, ['state' => 'draft']));

    // Note: Moderator role does not have 'create assessment report' permission.
    // If moderator needs to create, they would need authenticated role as well.
  }

  /**
   * Tests create access for published assessment reports.
   *
   * Create in published state is allowed for
   * "bypass assessment report moderation" permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreatePublished(): void {
    $accessHandler = \Drupal::entityTypeManager()->getAccessControlHandler('assessment');

    // Assessment reporter with bypass moderation can create published.
    $this->assertTrue($accessHandler->createAccess('report', $this->assessmentReporterUser, ['state' => 'published']));

    // Moderator also has 'bypass assessment report moderation' permission.
    $this->assertTrue($accessHandler->createAccess('report', $this->moderatorUser, ['state' => 'published']));

    // Admin can create published.
    $this->assertTrue($accessHandler->createAccess('report', $this->admin, ['state' => 'published']));

    // Authenticated user without bypass cannot create published.
    $this->assertFalse($accessHandler->createAccess('report', $this->authenticatedUser, ['state' => 'published']));

    // User with only 'access content' cannot create published.
    $this->assertFalse($accessHandler->createAccess('report', $this->contentAccessUser, ['state' => 'published']));
  }

  /**
   * Tests edit/update access for draft assessment reports.
   *
   * Edit in draft state is allowed for "edit own assessment report"
   * (checks current user is owner).
   *
   * @covers ::checkAccess
   * @covers ::checkUpdateAccess
   */
  public function testEditDraft(): void {
    $assessment = $this->testAssessments['draft'];

    // Owner with 'edit own assessment report' can edit their own draft.
    $this->assertTrue($assessment->access('update', $this->authenticatedUser), "Owner should edit draft");

    // Non-owner with 'edit own' permission cannot edit.
    $this->assertFalse($assessment->access('update', $this->otherAuthenticatedUser), "Non-owner with 'edit own' should not edit draft");

    // Admin can edit.
    $this->assertTrue($assessment->access('update', $this->admin), "Admin should edit draft");

    // User without 'edit own assessment report' cannot edit.
    $this->assertFalse($assessment->access('update', $this->contentAccessUser), "User without 'edit own' should not edit draft");
  }

  /**
   * Tests moderate/update access for review assessment reports.
   *
   * Moderate in review state is allowed for
   * "moderate assessment report" permission.
   *
   * @covers ::checkAccess
   * @covers ::checkUpdateAccess
   */
  public function testModerateReview(): void {
    $assessment = $this->testAssessments['review'];

    // User with 'moderate' permission can moderate in review.
    $this->assertTrue($assessment->access('update', $this->moderatorUser), "Moderator should moderate review report");

    // Admin can moderate.
    $this->assertTrue($assessment->access('update', $this->admin), "Admin should moderate review report");

    // Owner cannot edit their own report in review state (only moderators can).
    $this->assertFalse($assessment->access('update', $this->authenticatedUser), "Owner should not edit review report");

    // Another authenticated user cannot edit.
    $this->assertFalse($assessment->access('update', $this->otherAuthenticatedUser), "Other authenticated user should not edit review report");

    // User with only 'access content' cannot moderate.
    $this->assertFalse($assessment->access('update', $this->contentAccessUser), "User with only 'access content' should not moderate review report");
  }

  /**
   * Tests delete access for assessment reports in all states.
   *
   * Delete in all states (draft, review, published, require_updates) is allowed
   * for "moderate assessment report" permission.
   *
   * @covers ::checkAccess
   * @covers ::checkDeleteAccess
   */
  public function testDelete(): void {
    $states = ['draft', 'review', 'published', 'require_updates'];

    foreach ($states as $state) {
      $assessment = $this->testAssessments[$state];

      // User with 'moderate assessment report' can delete in all states.
      $this->assertTrue($assessment->access('delete', $this->moderatorUser), "Moderator should delete $state report");

      // Admin can delete.
      $this->assertTrue($assessment->access('delete', $this->admin), "Admin should delete $state report");

      // Owner cannot delete their own report.
      $this->assertFalse($assessment->access('delete', $this->authenticatedUser), "Owner should not delete $state report");

      // Another authenticated user cannot delete.
      $this->assertFalse($assessment->access('delete', $this->otherAuthenticatedUser), "Other authenticated user should not delete $state report");

      // User with only 'access content' cannot delete.
      $this->assertFalse($assessment->access('delete', $this->contentAccessUser), "User with only 'access content' should not delete $state report");

      // Assessment reporter with bypass cannot delete (needs moderate).
      $this->assertFalse($assessment->access('delete', $this->assessmentReporterUser), "Assessment reporter with bypass moderation should not delete $state report");
    }
  }

  /**
   * Creates a user with the given role.
   *
   * @param string $role_id
   *   The role ID.
   * @param array $permissions
   *   Optional array of permissions if creating a custom role.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  protected function createUserWithRole(string $role_id, array $permissions = []): UserInterface {
    // If permissions are provided, create a user with an ad-hoc role.
    if (!empty($permissions)) {
      return $this->createUser($permissions);
    }

    // Otherwise, create a user and assign the existing role.
    $user = $this->createUser([]);
    $user->addRole($role_id);
    $user->save();

    return $user;
  }

  /**
   * Creates an assessment type (bundle).
   *
   * @param string $id
   *   The bundle ID.
   * @param string $label
   *   The bundle label.
   */
  protected function createAssessmentType(string $id, string $label): void {
    $bundle = \Drupal::entityTypeManager()
      ->getStorage('assessment_type')
      ->create([
        'id' => $id,
        'label' => $label,
      ]);
    $bundle->save();
  }

  /**
   * Installs the state field for assessment reports.
   */
  protected function installStateField(): void {
    // Use simple string field to avoid workflow complexity.
    $field_storage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => 'state',
        'entity_type' => 'assessment',
        'type' => 'string',
        'cardinality' => 1,
      ]);
    $field_storage->save();

    $field = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->create([
        'field_storage' => $field_storage,
        'bundle' => 'report',
        'label' => 'State',
        'default_value' => [
          ['value' => 'draft'],
        ],
      ]);
    $field->save();
  }

}
