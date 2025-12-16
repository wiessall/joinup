<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\assessments\Entity\AssessmentReport;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\assessments\NotificationEvents;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Form handler for the assessment moderation form.
 */
final class AssessmentModerationForm extends FormBase {

  public function __construct(
    protected readonly EventDispatcherInterface $eventDispatcher,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Checks moderate access for assessment reports.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $entity = $route_match->getParameter('assessment');

    if (!$entity instanceof AssessmentInterface) {
      return AccessResult::forbidden('Invalid assessment entity.')
        ->addCacheContexts(['route']);
    }

    // Only available for workflow-enabled bundles (i.e.: report).
    if (!$entity instanceof EntityWorkflowStateInterface) {
      return AccessResult::forbidden('This assessment bundle does not support moderation.')
        ->addCacheableDependency($entity);
    }

    // Moderation is allowed only when the assessment is in review.
    return AccessResult::allowedIf($entity->getWorkflowState() === 'review')
      ->andIf(AccessResult::allowedIfHasPermission($account, 'moderate assessment report'))
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'assessment_moderation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AssessmentInterface $assessment = NULL): array {
    assert($assessment instanceof AssessmentReport);
    $form_state->set('assessment', $assessment);

    $view_builder = $this->entityTypeManager->getViewBuilder('assessment');
    $form['assessment_display'] = [
      '#type' => 'item',
      '#title' => $this->t('Assessment Report'),
      '#markup' => '',
      '#weight' => -100,
    ];
    $form['assessment_display']['view'] = $view_builder->view($assessment, 'full');

    $actions_weight = 100;

    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What updates are needed?'),
      '#description' => $this->t('Provide feedback to the submitter about what needs to be updated.'),
      '#weight' => $actions_weight,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => $actions_weight + 1,
    ];

    $form['actions']['draft'] = [
      '#type' => 'submit',
      '#value' => $this->t('Require updates'),
      '#state_id' => 'draft',
    ];

    $form['actions']['published'] = [
      '#type' => 'submit',
      '#value' => $this->t('Publish'),
      '#state_id' => 'published',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $target_state = $triggering_element['#state_id'] ?? NULL;
    if ($target_state !== 'draft') {
      return;
    }

    $reason = trim((string) $form_state->getValue('reason', ''));
    if (empty($reason)) {
      $form_state->setErrorByName('reason', $this->t('When requesting updates, you must provide a reason.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\assessments\AssessmentInterface $assessment */
    $assessment = $form_state->get('assessment');

    $triggering_element = $form_state->getTriggeringElement();
    $new_state = $triggering_element['#state_id'] ?? NULL;

    if ($new_state) {
      $assessment->set('state', $new_state);
      $assessment->save();
    }

    $reason = trim((string) $form_state->getValue('reason', ''));

    if ($new_state === 'published') {
      $this->messenger()
        ->addStatus($this->t('The assessment report has been published.'));
      $event = new AssessmentReportEvent($assessment);
      $this->eventDispatcher->dispatch($event, NotificationEvents::ASSESSMENT_REPORT_PUBLISHED);
    }
    elseif ($new_state === 'draft') {
      $this->messenger()
        ->addStatus($this->t('The submitter has been notified that updates are required.'));
      $event = new AssessmentReportEvent($assessment, $reason);
      $this->eventDispatcher->dispatch($event, NotificationEvents::ASSESSMENT_REPORT_REQUIRE_UPDATES);
    }
    else {
      $this->messenger()
        ->addStatus($this->t('The assessment report has been updated.'));
    }

    $form_state->setRedirectUrl($assessment->toUrl());
  }

}
