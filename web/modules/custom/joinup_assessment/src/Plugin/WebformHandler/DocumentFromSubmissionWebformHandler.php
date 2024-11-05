<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Plugin\WebformHandler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\webformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The Layouts Manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected LayoutPluginManagerInterface $layoutPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->layoutPluginManager = $container->get('plugin.manager.core.layout');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
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
      'field_paragraphs_body' => [$this->getLayoutParagraph()],
      'field_state' => AssessmentInterface::DOCUMENT_STATE,
      'og_audience' => AssessmentInterface::DOCUMENT_GROUP_URI,
      'abstract' => substr($submissionData[AssessmentInterface::SUBMISSION_ABSTRACT] ?? '', 0, 250),
      'field_type' => AssessmentInterface::DOCUMENT_TYPE_KEY,
      'field_topic' => $this->getTopicId(),
    ]);
    $node->save();

    $assessment = $metaStorage->create([
      'type' => 'assessment',
      'target' => $node,
      'submission' => $webformSubmission->id(),
    ]);
    $assessment->save();
  }

  /**
   * Gets topic id.
   *
   * @return string|null
   *   Topic id.
   */
  protected function getTopicId(): ?string {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('name', AssessmentInterface::DOCUMENT_TOPIC)
      ->condition('vid', 'topic')
      ->range(0, 1);

    $tids = $query->execute();
    return !empty($tids) ? reset($tids) : NULL;
  }

  /**
   * Generate layout paragraph.
   *
   * @param string $layout_id
   *   Layout name.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Paragraph entity.
   */
  protected function getLayoutParagraph(string $layout_id = 'layout_onecol'): EntityInterface {
    $layoutInstance = $this->layoutPluginManager->createInstance($layout_id);
    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');

    $layoutParagraph = $paragraphStorage->create([
        'type' => 'layout',
      ]
    );
    $layoutParagraph->setBehaviorSettings(
      'layout_paragraphs',
      [
        'layout' => $layout_id,
        'config' => $layoutInstance->getConfiguration(),
      ]
    );

    return $layoutParagraph;
  }

}
