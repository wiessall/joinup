<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Step definitions for assessments checks.
 */
class AssessmentContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Navigates to the latest assessment webform submission.
   *
   * @When I go to the latest assessment submission
   */
  public function visitLatestAssessmentCanonicalPath(): void {
    $query = \Drupal::entityQuery('webform_submission')
      ->accessCheck(FALSE)
      ->condition('webform_id', 'online_form_for_assessments_demo')
      ->sort('created', 'DESC')
      ->range(0, 1);

    $submissionIds = $query->execute();
    if (empty($submissionIds)) {
      throw new \Exception('The latest assessment submission was not found.');
    }

    $submission = $this->getEntityById('webform_submission', reset($submissionIds));
    $this->visitPath($submission->toUrl()->toString());
  }

  /**
   * Navigates to the latest assessment.
   *
   * @When I go to the latest document assessment
   */
  public function visitLatestDocumentAssessment(): void {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'document')
      ->condition('title', AssessmentInterface::DOCUMENT_TITLE_PREFIX . '%', 'LIKE')
      ->sort('created', 'DESC')
      ->range(0, 1);

    $nids = $query->execute();
    if (empty($nids)) {
      throw new \Exception('The latest assessment was not found.');
    }

    $node = $this->getEntityById('node', reset($nids));
    $this->visitPath($node->toUrl()->toString());
  }

  /**
   * Cleans up after the assessment test.
   *
   * @AfterScenario @demoAssessment
   */
  public function cleanSubmissions(): void {
    $submissionIds = \Drupal::entityQuery('webform_submission')
      ->accessCheck(FALSE)
      ->condition('webform_id', 'online_form_for_assessments_demo')
      ->execute();

    foreach ($submissionIds as $submissionId) {
      $submission = WebformSubmission::load($submissionId);
      if ($submission) {
        $submission->delete();
      }
    }
  }

}
