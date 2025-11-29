<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Core\Session\AccountInterface;
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
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    foreach ($this->testAssessments as $assessment) {
      if ($assessment && !$assessment->isNew()) {
        $assessment->delete();
      }
    }

    parent::tearDown();
  }

  /**
   * Tests view access for published assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkViewAccess
   */
  public function testViewPublishedAssessment(): void {
    $assessment = $this->testAssessments['published'];

    $this->assertAccessAllowed($assessment, 'view', $this->ownerUser);
    $this->assertAccessAllowed($assessment, 'view', $this->moderatorUser);
    $this->assertAccessAllowed($assessment, 'view', $this->authenticatedUser);
    $this->assertAccessAllowed($assessment, 'view', User::getAnonymousUser());
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

    $this->assertAccessAllowed($assessment, 'view', $this->ownerUser);
    $this->assertAccessAllowed($assessment, 'view', $this->moderatorUser);

    $this->assertAccessDenied($assessment, 'view', $this->nonOwnerUser);
    $this->assertAccessDenied($assessment, 'view', $this->authenticatedUser);
  }

  /**
   * Tests create access for draft assessment reports.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateDraftAssessment(): void {
    $this->assertCreateAccessAllowed($this->ownerUser, 'draft');
    $this->assertCreateAccessAllowed($this->assessmentReporterUser, 'draft');
    $this->assertCreateAccessAllowed($this->moderatorUser, 'draft');
    $this->assertCreateAccessAllowed($this->authenticatedUser, 'draft');

    $this->assertCreateAccessDenied(User::getAnonymousUser(), 'draft');
  }

  /**
   * Tests create access for published assessment reports.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreatePublishedAssessment(): void {
    $this->assertCreateAccessAllowed($this->assessmentReporterUser, 'published');
    $this->assertCreateAccessAllowed($this->moderatorUser, 'published');

    $this->assertCreateAccessDenied($this->ownerUser, 'published');
    $this->assertCreateAccessDenied($this->authenticatedUser, 'published');
  }

  /**
   * Tests update access for draft assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkUpdateAccess
   */
  public function testUpdateDraftAssessment(): void {
    $assessment = $this->testAssessments['draft'];

    $this->assertAccessAllowed($assessment, 'update', $this->ownerUser);
    $this->assertAccessAllowed($assessment, 'update', $this->moderatorUser);

    $this->assertAccessDenied($assessment, 'update', $this->nonOwnerUser);
    $this->assertAccessDenied($assessment, 'update', $this->authenticatedUser);
  }

  /**
   * Tests update access for review state assessment reports.
   *
   * @covers ::checkAccess
   * @covers ::checkUpdateAccess
   */
  public function testUpdateReviewAssessment(): void {
    $assessment = $this->testAssessments['review'];

    $this->assertAccessAllowed($assessment, 'update', $this->moderatorUser);

    $this->assertAccessDenied($assessment, 'update', $this->ownerUser);
    $this->assertAccessDenied($assessment, 'update', $this->nonOwnerUser);
    $this->assertAccessDenied($assessment, 'update', $this->authenticatedUser);
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

    $this->assertAccessAllowed($assessment, 'delete', $this->moderatorUser);

    $this->assertAccessDenied($assessment, 'delete', $this->ownerUser);
    $this->assertAccessDenied($assessment, 'delete', $this->nonOwnerUser);
    $this->assertAccessDenied($assessment, 'delete', $this->authenticatedUser);
    $this->assertAccessDenied($assessment, 'delete', $this->assessmentReporterUser);
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
   * Asserts that access is allowed.
   *
   * @param \Drupal\assessments\Entity\Assessment $assessment
   *   The assessment entity.
   * @param string $operation
   *   The operation (view, update, delete).
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   */
  protected function assertAccessAllowed(Assessment $assessment, string $operation, AccountInterface $account): void {
    $this->assertTrue(
      $assessment->access($operation, $account),
      sprintf(
        'User %s (%s) should have %s access to %s assessment',
        $account->getAccountName(),
        implode(', ', $account->getRoles()),
        $operation,
        $assessment->get('state')->value
      )
    );
  }

  /**
   * Asserts that access is denied.
   *
   * @param \Drupal\assessments\Entity\Assessment $assessment
   *   The assessment entity.
   * @param string $operation
   *   The operation (view, update, delete).
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   */
  protected function assertAccessDenied(Assessment $assessment, string $operation, AccountInterface $account): void {
    $this->assertFalse(
      $assessment->access($operation, $account),
      sprintf(
        'User %s (%s) should not have %s access to %s assessment',
        $account->getAccountName(),
        implode(', ', $account->getRoles()),
        $operation,
        $assessment->get('state')->value
      )
    );
  }

  /**
   * Asserts that create access is allowed.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   * @param string $state
   *   The target state for the new assessment.
   */
  protected function assertCreateAccessAllowed(AccountInterface $account, string $state): void {
    $this->assertTrue(
      $this->accessHandler->createAccess('report', $account, ['state' => $state]),
      sprintf(
        'User %s (%s) should have create access for %s assessment',
        $account->getAccountName(),
        implode(', ', $account->getRoles()),
        $state
      )
    );
  }

  /**
   * Asserts that create access is denied.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check.
   * @param string $state
   *   The target state for the new assessment.
   */
  protected function assertCreateAccessDenied(AccountInterface $account, string $state): void {
    $this->assertFalse(
      $this->accessHandler->createAccess('report', $account, ['state' => $state]),
      sprintf(
        'User %s (%s) should not have create access for %s assessment',
        $account->getAccountName(),
        implode(', ', $account->getRoles()),
        $state
      )
    );
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

}
