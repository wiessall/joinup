<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup_assessment\AssessmentInterface as LegacyAssessmentInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Step definitions for joinup_assessment checks.
 */
class JoinupAssessmentContext extends RawDrupalContext {

  use EntityTrait;
  use UtilityTrait;

  /**
   * Stores the latest submission ID.
 */
  #[BeforeScenario('@demoAssessment')]
  public function beforeAssessmentsDemo(): void {
    $collection = \Drupal::service('entity.repository')->loadEntityByUuid('node', LegacyAssessmentInterface::GROUP_UUID);
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
    $this->nodeCreate((object) [
      'type' => 'custom_page',
      'title' => 'Assessment reports',
      'og_audience' => $collection->label(),
      'field_state' => 'published',
      'uuid' => LegacyAssessmentInterface::ASSESSMENT_REPORTS_UUID,
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
      ->condition('webform_id', LegacyAssessmentInterface::WEBFORM_ID)
      ->sort('sid', 'DESC')
      ->range(0, 1)
      ->execute();
    return $sids ? (int) reset($sids) : NULL;
  }

  /**
   * Creates submission if not exist and updates selected submission data.
   *
   * @param string $fieldName
   *   Webform field name.
   * @param string $value
   *   New value for webform field.
   * @param string $assessment
   *   Title of assessment_report entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  #[When('I set :fieldName as :value for :assessment assessment_report submission')]
  public function setFieldValueForAssesmentSubmission(string $fieldName, string $value, string $assessment): void {
    $assessment_report = $this->getEntityByLabel('node', $assessment);
    $submission = $assessment_report->submission->entity ?? $this->createSubmissionForAssessmentReport($assessment);
    $submission->setData([$fieldName => $value]);
    $submission->save();
  }

  /**
   * Creates new submission for assessment_report entity.
   *
   * @param string $assessment_report_title
   *   Assessment report title.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSubmissionForAssessmentReport(string $assessment_report_title): WebformSubmissionInterface {
    $webform = Webform::load(LegacyAssessmentInterface::WEBFORM_ID);
    $submission = WebformSubmission::create([
      'webform_id' => $webform->id(),
      'uid' => \Drupal::currentUser()->id(),
      'remote_addr' => \Drupal::request()->getClientIp(),
    ]);
    $submission->save();
    $assessment_report = $this->getEntityByLabel('node', $assessment_report_title);
    $assessment_report->set('submission', $submission);
    $assessment_report->save();
    return $submission;
  }

  /**
   * Asserts that an assessment with the given name and type does not exist.
   *
   * @param string $type
   *   The type of the assessment.
   * @param string $name
   *   The name of the assessment.
   *
   * @throws \Exception
   *   When an assessment with the given name and type exists.
   */
  #[Then('the :type assessment :name should not exist')]
  public function assertAssessmentNotExists(string $type, string $name): void {
    try {
      $this->getEntityByLabel('assessment', $name, $type);
      // If we reach here, the entity exists, so throw an exception.
      throw new \Exception(sprintf('A %s assessment with name "%s" exists but should not.', $type, $name));
    }
    catch (\RuntimeException) {
      // Expected: entity not found. This is what we want.
    }
  }

}
