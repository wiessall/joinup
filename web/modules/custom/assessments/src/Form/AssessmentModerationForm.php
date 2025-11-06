<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\assessments\NotificationEvents;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Form handler for the assessment moderation form.
 */
final class AssessmentModerationForm extends ContentEntityForm {

  public function __construct(
    protected readonly EventDispatcherInterface $eventDispatcher,
    EntityRepositoryInterface $entityRepository,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    TimeInterface $time,
  ) {
    parent::__construct($entityRepository, $entityTypeBundleInfo, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('event_dispatcher'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\assessments\AssessmentInterface $assessment */
    $assessment = $this->entity;

    if ($assessment->bundle() !== 'report' || $assessment->get('state')->value !== 'review') {
      $this->messenger()->addError($this->t('This assessment report is not available for moderation.'));
      return [];
    }

    $form = parent::buildForm($form, $form_state);

    $actions_weight = $form['actions']['#weight'] ?? 100;

    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What updates are needed?'),
      '#description' => $this->t('Provide feedback to the submitter about what needs to be updated.'),
      '#weight' => $actions_weight,
    ];

    $form['actions']['#weight'] = $actions_weight + 1;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\assessments\AssessmentInterface $assessment */
    $assessment = $this->entity;
    $current_state = $assessment->get('state')->value;

    $triggering_element = $form_state->getTriggeringElement();
    $target_state = $triggering_element['#state_id'] ?? NULL;

    if ($current_state !== 'review' || $target_state !== 'draft') {
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
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\assessments\AssessmentInterface $assessment */
    $assessment = $this->entity;
    $previous_state = $assessment->get('state')->value;

    $result = parent::save($form, $form_state);

    $new_state = $assessment->get('state')->value;
    $reason = trim((string) $form_state->getValue('reason', ''));

    // Handle state transitions: show messages and dispatch events.
    if ($new_state === 'published' && $previous_state !== 'published') {
      $this->messenger()
        ->addStatus($this->t('The assessment report has been published.'));
      $event = new AssessmentReportEvent($assessment);
      $this->eventDispatcher->dispatch($event, NotificationEvents::ASSESSMENT_REPORT_PUBLISHED);
    }
    elseif ($new_state === 'draft' && $previous_state === 'review') {
      $this->messenger()
        ->addStatus($this->t('The submitter has been notified that updates are required.'));
      $event = new AssessmentReportEvent($assessment, $reason);
      $this->eventDispatcher->dispatch($event, NotificationEvents::ASSESSMENT_REPORT_REQUIRE_UPDATES);
    }
    else {
      $this->messenger()
        ->addStatus($this->t('The assessment report has been updated.'));
    }

    $form_state->setRedirectUrl($assessment->toUrl('collection'));

    return $result;
  }

}
