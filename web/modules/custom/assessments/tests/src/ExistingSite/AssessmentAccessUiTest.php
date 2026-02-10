<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\Entity\Assessment;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests assessments access.
 *
 * @group assessments
 */
class AssessmentAccessUiTest extends JoinupExistingSiteTestBase {

  /**
   * Tests canonical pages access restrictions.
   */
  public function testAssessmentPagesAccess(): void {
    $this->drupalLogin($this->createUser());

    // Assessment reports canonical pages should be accessible.
    $report = Assessment::create([
      'type' => 'report',
      'name' => 'Test Report',
      'state' => 'published',
    ]);
    $report->save();
    $this->markEntityForCleanup($report);
    $this->drupalGet($report->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Other assessment types should not expose a canonical page.
    $assessment_types = ['public_organisation', 'public_service', 'expression', 'participation'];
    foreach ($assessment_types as $assessment_type) {
      $assessment = Assessment::create([
        'type' => $assessment_type,
        'status' => TRUE,
      ]);
      $assessment->save();
      $this->markEntityForCleanup($assessment);
      $this->drupalGet($assessment->toUrl());
      $this->assertSession()->statusCodeEquals(404);
    }
  }

  /**
   * Tests canonical pages access restrictions.
   */
  public function testTaxonomyTermPagesAccess(): void {
    $this->drupalLogin($this->createUser());

    // Assessment-related taxonomy term pages should not be accessible.
    $vids = [
      'assess_participant_type',
      'assess_provider_type',
      'assess_public_service_area',
      'assess_statement_interpretation',
      'assess_statement_type',
    ];
    foreach ($vids as $vid) {
      $term = $this->createTerm(Vocabulary::load($vid));
      $this->drupalGet($term->toUrl());
      $this->assertSession()->statusCodeEquals(403);
    }
  }

}
