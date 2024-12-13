<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Step definitions for assessments checks.
 */
class AssessmentContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Latest submission ID before scenario.
   */
  protected int $latestSubmissionId;

  /**
   * Navigates to the latest assessment webform submission.
   *
   * @When I go to the latest assessment submission
   */
  public function visitLatestAssessmentCanonicalPath(): void {
    $query = \Drupal::entityQuery('webform_submission')
      ->accessCheck(FALSE)
      ->condition('webform_id', AssessmentInterface::WEBFORM_ID)
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
   * Navigates to the latest submission edit form.
   *
   * @When I go to the latest assessment submission edit form
   */
  public function visitLatestSubmissionEditForm(): void {
    $this->visitPath($this->getLatestSubmission()->toUrl('edit-form')->toString());
  }

  /**
   * Navigates to the latest submission edit form.
   *
   * @When I go to the latest submission moderation form
   */
  public function visitLatestSubmissionModerateForm(): void {
    $url = Url::fromRoute('joinup_assessment.moderation', [
      'submission' => $this->getLatestSubmission()->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Navigates to the latest assessment report.
   *
   * @When I go to the latest assessment report
   */
  public function visitLatestAssessmentReport(): void {
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'assessment_report')
      ->condition('organization', 'my organization')
      ->sort('nid', 'DESC')
      ->range(0, 1)
      ->execute();

    if (empty($nids)) {
      throw new \Exception('The latest assessment was not found.');
    }

    $node = $this->getEntityById('node', reset($nids));

    $this->visitPath($node->toUrl()->toString());
  }

  /**
   * Stores the latest submission ID.
   *
   * @BeforeScenario @demoAssessment
   */
  public function storeLatestSubmissionId(): void {
    $submissionIds = \Drupal::entityQuery('webform_submission')
      ->accessCheck(FALSE)
      ->condition('webform_id', AssessmentInterface::WEBFORM_ID)
      ->sort('sid', 'DESC')
      ->range(0, 1)
      ->execute();
    $this->latestSubmissionId = $submissionIds ? (int) reset($submissionIds) : 0;
  }

  /**
   * Cleans up after the assessment test.
   *
   * @AfterScenario @demoAssessment
   */
  public function cleanSubmissions(): void {
    $submissionIds = \Drupal::entityQuery('webform_submission')
      ->accessCheck(FALSE)
      ->condition('webform_id', AssessmentInterface::WEBFORM_ID)
      ->condition('sid', $this->latestSubmissionId, '>')
      ->execute();

    $metaEntityStorage = \Drupal::entityTypeManager()->getStorage('meta_entity');
    foreach ($submissionIds as $submissionId) {
      $submission = WebformSubmission::load($submissionId);
      if ($submission) {
        $submission->delete();
        // Remove also the corresponding assessment reports.
        $ids = $metaEntityStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', AssessmentInterface::WEBFORM_ID)
          ->condition('submission', $submissionId)
          ->execute();
        if ($ids) {
          $metaEntity = $metaEntityStorage->load(reset($ids));
          assert($metaEntity instanceof MetaEntityInterface);
          $metaEntity->getTargetEntity()->delete();
        }
      }
    }
  }

  /**
   * Returns the latest submission entity.
   *
   * @return \Drupal\webform\WebformSubmissionInterface
   *   The webform submission.
   */
  protected function getLatestSubmission(): WebformSubmissionInterface {
    $sids = \Drupal::entityQuery('webform_submission')
      ->accessCheck(FALSE)
      ->condition('webform_id', AssessmentInterface::WEBFORM_ID)
      ->sort('sid', 'DESC')
      ->range(0, 1)
      ->execute();

    if (!$sids) {
      throw new \Exception("Cannot find any 'assessment' webform submission.");
    }

    $submission = WebformSubmission::load(reset($sids));
    assert($submission instanceof WebformSubmissionInterface);
    return $submission;
  }

  /**
   * Navigate to the entity print download link of the latest submission.
   *
   * @When I go to the pdf download link of the latest assessment submission
   */
  public function visitLatestSubmissionPdfDownloadLink(): void {
    $submission = $this->getLatestSubmission();
    $url = Url::fromRoute('entity_print.view', [
      'export_type' => 'pdf',
      'entity_type' => 'webform_submission',
      'entity_id' => $submission->id(),
    ]);

    $this->visitPath($url->toString());
  }

}
