<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentStatement;

/**
 * Tests the assessment statement access control handler.
 *
 * @coversDefaultClass \Drupal\assessments\AssessmentStatementAccessControlHandler
 */
class AssessmentStatementAccessTest extends JoinupExistingSiteTestBase {

  /**
   * Tests update access for assessment statements delegates to parent report.
   *
   * @covers ::checkAccess
   */
  public function testUpdateAccessDelegatesToParentReport(): void {
    $owner = $this->createUser();
    $nonOwner = $this->createUser();

    // Create a draft report owned by $owner.
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Test Report',
      'state' => 'draft',
      'uid' => $owner->id(),
    ]);
    $report->save();
    $this->markEntityForCleanup($report);

    // Create a statement and link it to the report.
    $statement = AssessmentStatement::create(['type' => 'legal']);
    $statement->save();
    $this->markEntityForCleanup($statement);
    $report->set('legal', $statement->id())->save();

    // Owner can update draft report, so they can update the statement.
    $this->assertTrue($statement->access('update', $owner));
    // Non-owner cannot update draft report, so cannot update the statement.
    $this->assertFalse($statement->access('update', $nonOwner));

    // Orphan statement (no parent report) cannot be updated.
    $orphan = AssessmentStatement::create(['type' => 'semantic']);
    $orphan->save();
    $this->markEntityForCleanup($orphan);
    $this->assertFalse($orphan->access('update', $owner));

    // Moderators can access anyway.
    $admin = $this->createUser();
    $admin->addRole('assessment_moderator');
    $admin->save();
    // Assert that the user has the role.
    $this->assertTrue($admin->hasRole('assessment_moderator'));
    $this->assertTrue($statement->access('update', $admin));
    $this->assertTrue($orphan->access('update', $admin));
  }

}
