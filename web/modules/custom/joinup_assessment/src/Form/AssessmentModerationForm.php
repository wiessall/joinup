<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\joinup_assessment\Entity\AssessmentReportInterface;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
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
          '@assessment:submission_edit_url' => Url::fromRoute('joinup_assessment.edit', [
            'webform_submission' => $submission->id(),
          ])->setAbsolute()->toString(),
        ];
        $this->delivery->sendMessageTemplateToUser('assessment_require_update', $arguments, $submission->getOwner());
        $message = $this->t('The assessment submission requires updates. The submitter has been notified.');
        break;

      case 'delete':
        $submission->delete();
        $message = $this->t('The assessment submission has been deleted.');
        break;

      case 'publish':
        $assessmentReport = $this->createAssessmentReport($submission);
        // Normally, the submission is already locked but let's make sure.
        if (!$submission->isLocked()) {
          $submission->setLocked(TRUE)->save();
        }
        $this->delivery->sendMessageTemplateToUser('assessment_published', [
          '@assessment:report_url' => $assessmentReport->toUrl()->setAbsolute()->toString(),
          '@assessment:reports_url' => Url::fromRoute('view.assessment_reports.list')->setAbsolute()->toString(),
        ], $submission->getOwner());
        $message = $this->t('The assessment submission report has been created: <a href=":href">@title</a>.', [
          ':href' => $assessmentReport->toUrl()->toString(),
          '@title' => $assessmentReport->label(),
        ]);
        break;

      default:
        throw new \InvalidArgumentException("Unknown action '$action'");
    }

    $form_state->setRedirect('view.assessment_reports.list');
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

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $hasReport = (bool) $query->accessCheck(FALSE)
      ->condition('type', 'assessment_report')
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
   * Creates an assessment report out of a submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   *
   * @return \Drupal\joinup_assessment\Entity\AssessmentReportInterface
   *   The assessment report.
   */
  protected function createAssessmentReport(WebformSubmissionInterface $submission): AssessmentReportInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    if (!$group = $this->entityRepository->loadEntityByUuid('node', AssessmentInterface::GROUP_UUID)) {
      throw new \RuntimeException("Group with UUID '" . AssessmentInterface::GROUP_UUID . "' was not found");
    }
    $organisation = $submission->getData()['organisation'];
    if (in_array($submission->getData()['public_sector_body'], [
      'Local public sector body',
      'Regional public sector body',
      'National public sector body',
    ], TRUE) && !empty($submission->getData()['country'])) {
      $organisation .= ' (' . $submission->getData()['country'] . ')';
    }

    $assessmentReport = $nodeStorage->create([
      'type' => 'assessment_report',
      'title' => sprintf(AssessmentInterface::REPORT_TITLE_PATTERN, $submission->id()),
      'organization' => $organisation,
      'og_audience' => $group,
      'submission' => $submission->id(),
    ]);

    assert($assessmentReport instanceof AssessmentReportInterface);
    $assessmentReport->save();
    return $assessmentReport;
  }

}
