<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Plugin\WebformHandler;

use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\webformSubmissionInterface;

/**
 * Create a new Document node from a webform submission.
 *
 * @WebformHandler(
 *   id = "document_from_webform",
 *   label = @Translation("Create a document on submit"),
 *   category = @Translation("Content"),
 *   description = @Translation("Creates a document from submissions."),
 *   cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class DocumentFromSubmissionWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    if ($webform_submission->getState() === WebformSubmissionInterface::STATE_COMPLETED) {
      $this->executeAction($webform_submission);
    }
  }

  /**
   * Execute this action.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   A webform submission.
   */
  protected function executeAction(WebformSubmissionInterface $webformSubmission): void {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $metaStorage = $this->entityTypeManager->getStorage('meta_entity');

    $submissionData = $webformSubmission->getData();

    $node = $nodeStorage->create([
      'type' => 'document',
      'title' => $webformSubmission->label(),
      'field_state' => AssessmentInterface::DOCUMENT_STATE,
      'og_audience' => AssessmentInterface::DOCUMENT_GROUP_URI,
      'abstract' => substr($submissionData[AssessmentInterface::SUBMISSION_ABSTRACT] ?? '', 0, 250),
      'field_type' => AssessmentInterface::DOCUMENT_TYPE_KEY,
      'field_topic' => AssessmentInterface::DOCUMENT_TOPIC_ID,
    ]);
    $node->save();

    $assessment = $metaStorage->create([
      'type' => 'assessment',
      'target' => $node,
      'submission' => $webformSubmission->id(),
    ]);
    $assessment->save();
  }

}
