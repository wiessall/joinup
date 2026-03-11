<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Tests\assessments\Traits\AssessmentEntityCreationTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\AssessmentStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the EntityReferenceFieldsOwnedTrait.
 */
#[CoversClass(AssessmentReport::class)]
#[Group('assessments')]
class EntityReferenceFieldsOwnedTraitTest extends JoinupExistingSiteTestBase {

  use AssessmentEntityCreationTrait;

  /**
   * Tests that referenced entities are deleted if not referenced elsewhere.
   */
  public function testEntityReferenceDeletion(): void {
    // Create two assessment reports.
    $reportA = Assessment::create([
      'type' => 'report',
      'name' => 'Report A',
      'state' => 'draft',
    ]);
    $reportA->save();
    $this->markEntityForCleanup($reportA);

    $reportB = Assessment::create([
      'type' => 'report',
      'name' => 'Report B',
      'state' => 'draft',
    ]);
    $reportB->save();
    $this->markEntityForCleanup($reportB);

    // Create a shared assessment statement.
    $statement = $this->createAssessmentStatement('legal');
    $statement->save();
    $this->markEntityForCleanup($statement);

    // Reference the statement from both reports.
    $reportA->set('legal', ['target_id' => $statement->id()]);
    $reportA->save();

    $reportB->set('legal', ['target_id' => $statement->id()]);
    $reportB->save();

    // Verify both reference the same statement.
    $this->assertNotEmpty($statement->id(), 'Statement ID should not be empty after creation.');
    $this->assertEquals($statement->id(), $reportA->get('legal')->target_id);
    $this->assertEquals($statement->id(), $reportB->get('legal')->target_id);

    // Update Report A to remove the reference.
    $reportA->set('legal', []);
    $reportA->save();

    // THE CURRENT BUG: Statement is deleted because
    // onUpdateEntityReferenceFieldsOwned sees it was removed from Report A and
    // deletes it, ignoring Report B.
    $storage = $this->container->get('entity_type.manager')->getStorage('assessment_statement');
    $loadedStatement = $storage->loadUnchanged($statement->id());

    // This assertion is expected to FAIL currently if the bug exists.
    $this->assertNotNull($loadedStatement, 'Shared statement should NOT be deleted when one report removes it but another still references it.');

    // Now delete Report B.
    $reportB->delete();

    // Now it should be deleted.
    $loadedStatement = $storage->loadUnchanged($statement->id());
    $this->assertNull($loadedStatement, 'Statement should be deleted when the last referencing report is deleted.');
  }

  /**
   * Creates an assessment statement of the given bundle.
   */
  private function createAssessmentStatement(string $bundle): AssessmentStatement {
    return AssessmentStatement::create([
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
  }

}
