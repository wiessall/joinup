<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Step definitions for assessments checks.
 */
class AssessmentContext extends RawDrupalContext {

  use EntityTrait;
  use TestingEntitiesTrait;
  use UtilityTrait;

  /**
   * Navigates to the latest assessment webform submission.
   *
   * @When I go to the latest assessment submission
   */
  public function visitLatestAssessmentCanonicalPath(): void {
    $this->visitPath($this->getLatestSubmission()->toUrl()->toString());
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
      'submission' => $this->getLatestSubmissionId(),
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
  public function beforeAssessmentsDemo(): void {
    $collection = $this->nodeCreate((object) [
      'type' => 'collection',
      'uuid' => AssessmentInterface::GROUP_UUID,
      'title' => 'Interoperability assessments space',
      'short_id' => 'assessments',
      'field_state' => 'published',
    ]);

    $paragraph = Paragraph::create([
      'type' => 'block',
      'paragraph_block' => [
        'plugin_id' => 'views_block:assessment_reports-list',
        'settings' => [
          'id' => 'views_block:assessment_reports-list',
          'label' => '',
          'label_display' => FALSE,
          'provider' => 'views',
          'views_label' => '',
          'items_per_page' => 10,
        ],
      ],
    ]);
    $paragraph->save();
    $this->entities['paragraph'][$paragraph->id()] = $paragraph;
    $this->nodeCreate((object) [
      'type' => 'custom_page',
      'title' => 'Assessment reports',
      'og_audience' => $collection->title,
      'field_state' => 'published',
      'uuid' => AssessmentInterface::ASSESSMENT_REPORTS_UUID,
      'field_paragraphs_body:target_id' => $paragraph->id(),
      'field_paragraphs_body:target_revision_id' => $paragraph->getRevisionId(),
    ]);
  }

  /**
   * Returns the latest submission entity ID.
   *
   * @return int|null
   *   The latest webform submission ID.
   */
  protected function getLatestSubmissionId(): ?int {
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    $sids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', AssessmentInterface::WEBFORM_ID)
      ->sort('sid', 'DESC')
      ->range(0, 1)
      ->execute();
    return $sids ? (int) reset($sids) : NULL;
  }

  /**
   * Returns the latest submission entity.
   *
   * @return \Drupal\webform\WebformSubmissionInterface
   *   The webform submission.
   */
  protected function getLatestSubmission(): WebformSubmissionInterface {
    if ($submissionId = $this->getLatestSubmissionId()) {
      $submission = \Drupal::entityTypeManager()->getStorage('webform_submission')->load($submissionId);
      assert($submission instanceof WebformSubmissionInterface);
      return $submission;
    }
    throw new \Exception("Cannot find any 'assessment' webform submission.");
  }

  /**
   * Navigate to the entity print download link of the latest submission.
   *
   * @When I go to the pdf download link of the latest assessment submission
   */
  public function visitLatestSubmissionPdfDownloadLink(): void {
    $url = Url::fromRoute('entity_print.view', [
      'export_type' => 'pdf',
      'entity_type' => 'webform_submission',
      'entity_id' => $this->getLatestSubmissionId(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Creates submission if not exist and updates selected submission data.
   *
   * @When I set :fieldName as :value for :assessment assessment_report submission
   */
  public function setFieldValueForAssesmentSubmission(string $fieldName, string $value, string $assessment): void {
    $assessment_report = $this->getEntityByLabel('node', $assessment);
    if (empty($assessment_report->submission->entity)) {
      $submission = $this->createSubmissionForAssessmentReport($assessment);
    }
    else {
      $submission = $assessment_report->submission->entity;
    }
    $submission->setData([$fieldName => $value]);
    $submission->save();
  }

  /**
   * Creates new submission for assessment_report entity.
   *
   * @param string $assesment_report
   *   Assessment report title.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSubmissionForAssessmentReport(string $assesment_report): WebformSubmissionInterface {
    $webform = Webform::load(AssessmentInterface::WEBFORM_ID);
    $submission = WebformSubmission::create([
      'webform_id' => $webform->id(),
      'uid' => \Drupal::currentUser()->id(),
      'remote_addr' => \Drupal::request()->getClientIp(),
    ]);
    $submission->save();
    $assessment_report = $this->getEntityByLabel('node', $assesment_report);
    $assessment_report->set('submission', $submission);
    $assessment_report->save();
    return $submission;
  }

}
