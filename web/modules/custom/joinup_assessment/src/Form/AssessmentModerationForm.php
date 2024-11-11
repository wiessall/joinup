<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\joinup_document\Entity\DocumentInterface;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Drupal\topic\Entity\TopicInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformSubmissionViewBuilderInterface;

/**
 * Moderators form.
 */
class AssessmentModerationForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly LayoutPluginManagerInterface $layoutPluginManager,
    protected readonly JoinupMessageDeliveryInterface $delivery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WebformSubmissionInterface $submission = NULL): array {
    $initiative = $submission->getData()[AssessmentInterface::SUBMISSION_ABSTRACT] ?? '';
    $form['#title'] = $this->t('Assessment @initiative', ['@initiative' => $initiative]);

    if (!$submission->isCompleted()) {
      return [
        '#markup' => $this->t('This submission is not completed, thus it cannot be moderated yet.'),
      ];
    }

    $form['entity'] = [
      '#type' => 'value',
      '#value' => $submission,
    ];

    $form['submission'] = $this->buildSubmission($submission);

    $form['action'] = [
      '#type' => 'radios',
      '#title' => $this->t('Action'),
      '#required' => TRUE,
      '#options' => [
        'publish' => $this->t('Publish report'),
        'require_updates' => $this->t('Require updates'),
        'delete' => $this->t('Delete submission'),
      ],
    ];

    $state = ['input[name="action"]' => ['value' => 'require_updates']];
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What updates are needed?'),
      '#states' => ['visible' => $state, 'required' => $state],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('action') === 'require_updates') {
      $reason = trim($form_state->getValue('reason'));
      if (empty($reason)) {
        $form_state->setErrorByName('reason', $this->t('When updates are required, you should provide the reason.'));
      }
      $form_state->setValue('reason', $reason);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $submission = $form_state->getValue('entity');
    assert($submission instanceof WebformSubmissionInterface);

    $action = $form_state->getValue('action');
    switch ($action) {
      case 'require_updates':
        $reason = $form_state->getValue('reason');
        $submission
          ->set('in_draft', TRUE)
          ->setLocked(FALSE)
          ->set('completed', NULL)
          ->setCurrentPage(key($submission->getWebform()->getPages()))
          ->set('require_update_reason', $reason)
          ->save();

        $arguments = [
          '@assessment:require_update_reason' => $reason,
          '@assessment:submission_edit_url' => $submission->toUrl('edit-form')->setAbsolute()->toString(),
        ];
        $this->delivery->sendMessageTemplateToUser('assessment_require_update', $arguments, $submission->getOwner());
        $message = $this->t('The assessment submission requires updates. The submitter has been notified.');
        $form_state->setRedirect('joinup_assessment.edit', ['webform_submission' => $submission->id()]);
        break;

      case 'delete':
        $submission->delete();
        $message = $this->t('The assessment submission has been deleted.');
        $form_state->setRedirect('view.assessment_reports.list');
        break;

      case 'publish':
        $document = $this->createDocument($submission);
        // Normally, the submission is already locked but let's make sure.
        if (!$submission->isLocked()) {
          $submission->setLocked(TRUE)->save();
        }
        $this->delivery->sendMessageTemplateToUser('assessment_published', [
          '@assessment:report_url' => $document->toUrl()->setAbsolute()->toString(),
          '@assessment:reports_url' => Url::fromRoute('view.assessment_reports.list')->setAbsolute()->toString(),
        ], $submission->getOwner());
        $message = $this->t('The assessment submission report has been created: <a href=":href">@title</a>.', [
          ':href' => $document->toUrl()->toString(),
          '@title' => $document->label(),
        ]);
        $form_state->setRedirect('view.assessment_reports.list');
        break;

      default:
        throw new \InvalidArgumentException("Unknown action '$action'");
    }

    $this->messenger()->addStatus($message);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_assessment_moderation';
  }

  /**
   * Checks the 'joinup_assessment.moderation' route access.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access check result.
   */
  public function access(WebformSubmissionInterface $submission, AccountInterface $account): AccessResultInterface {
    $accessResult = AccessResult::allowedIf($submission->isCompleted())
      ->andIf(AccessResult::allowedIf($submission->isLocked()))
      ->andIf(AccessResult::allowedIfHasPermission($account, 'manage assessments'))
      ->addCacheableDependency($submission);

    if (!$accessResult->isAllowed()) {
      return $accessResult;
    }

    $query = $this->entityTypeManager->getStorage('meta_entity')->getQuery();
    $hasReport = (bool) $query->accessCheck(FALSE)
      ->condition('submission', $submission->id())
      ->count()
      ->execute();

    return $accessResult->andIf(AccessResult::allowedIf(!$hasReport));
  }

  /**
   * Builds the webform submission render array.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission entity.
   *
   * @return array
   *   Render array.
   */
  protected function buildSubmission(WebformSubmissionInterface $submission): array {
    $builder = $this->entityTypeManager->getHandler($submission->getEntityTypeId(), 'view_builder');
    assert($builder instanceof WebformSubmissionViewBuilderInterface);
    return $builder->view($submission);
  }

  /**
   * Creates a document out of a submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   *
   * @return \Drupal\joinup_document\Entity\DocumentInterface
   *   The report document.
   */
  protected function createDocument(WebformSubmissionInterface $submission): DocumentInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $metaStorage = $this->entityTypeManager->getStorage('meta_entity');

    if (!$group = $this->entityRepository->loadEntityByUuid('node', AssessmentInterface::GROUP_UUID)) {
      throw new \RuntimeException("Group with UUID " . AssessmentInterface::GROUP_UUID . "' was not found");
    }

    $abstract = substr($submission->getData()[AssessmentInterface::SUBMISSION_ABSTRACT] ?? '', 0, 250);

    $document = $nodeStorage->create([
      'type' => 'document',
      'title' => AssessmentInterface::DOCUMENT_TITLE_PREFIX . $submission->id(),
      'field_paragraphs_body' => [$this->getLayoutParagraph()],
      'field_state' => AssessmentInterface::DOCUMENT_STATE,
      'og_audience' => $group,
      'abstract' => $abstract,
      'field_type' => AssessmentInterface::DOCUMENT_TYPE_KEY,
      'field_topic' => $this->getTopic(),
    ]);
    assert($document instanceof DocumentInterface);
    $document->save();

    $assessment = $metaStorage->create([
      'type' => 'assessment',
      'target' => $document,
      'submission' => $submission->id(),
    ]);
    $assessment->save();

    return $document;
  }

  /**
   * Generates the layout paragraph.
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

    $layoutParagraph = $paragraphStorage->create(['type' => 'layout']);
    $layoutParagraph->setBehaviorSettings(
      'layout_paragraphs',
      [
        'layout' => $layout_id,
        'config' => $layoutInstance->getConfiguration(),
      ]
    );

    return $layoutParagraph;
  }

  /**
   * Gets the topic term entity.
   *
   * @return \Drupal\topic\Entity\TopicInterface
   *   The topic term.
   */
  protected function getTopic(): TopicInterface {
    $topic = $this->entityRepository->loadEntityByUuid('taxonomy_term', AssessmentInterface::TOPIC_UUID);
    assert($topic instanceof TopicInterface);
    return $topic;
  }

}
