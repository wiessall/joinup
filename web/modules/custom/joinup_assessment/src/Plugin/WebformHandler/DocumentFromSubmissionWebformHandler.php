<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Plugin\WebformHandler;

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

    $group = 'http://data.europa.eu/w21/c5e99eae-6d31-47a0-b914-a4b9f4ea82cf';

    $node = $nodeStorage->create([
      'type' => 'document',
      'status' => TRUE,
      'title' => $webformSubmission->label(),
      'og_audience' => $group,
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
