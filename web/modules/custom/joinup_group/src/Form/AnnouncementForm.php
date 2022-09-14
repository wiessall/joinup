<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to send an announcement message.
 */
class AnnouncementForm extends FormBase {

  /**
   * The announcement to be added/updated.
   */
  protected AnnouncementInterface $announcement;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Builds a new form instance.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('module_handler'),
    );
  }

  /**
   * Sets the announcement.
   *
   * @param \Drupal\joinup_group\Entity\AnnouncementInterface $announcement
   *   The announcement entity.
   *
   * @return $this
   */
  public function setAnnouncement(AnnouncementInterface $announcement): self {
    $this->announcement = $announcement;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_announcement_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $this->announcement->getSubject(),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['rdf_entity'],
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#description' => $this->t('Only plain text. HTML or markup are not allowed'),
      '#default_value' => $this->announcement->getBody(),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['rdf_entity'],
      '#required' => TRUE,
      '#cols' => 100,
      '#rows' => 10,
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['tokens'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['rdf_entity'],
        '#global_types' => TRUE,
        '#dialog' => TRUE,
      ];
    }

    $form['state'] = [
      '#markup' => $this->announcement->isNew() ? $this->t("Announcement not saved yet") : $this->t("Announcement in @state state", [
        '@state' => $this->announcement->getWorkflowStateInstance()->getLabel(),
      ]),
      '#prefix' => '<h3>',
      '#suffix' => '</h3>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->announcement->isNew() ? $this->t('Create') : $this->t('Update'),
    ];

    if (!$this->announcement->isNew()) {
      foreach ($this->announcement->getTransitions() as $transition) {
        $form['actions'][$transition->getId()] = [
          '#type' => 'submit',
          '#value' => $transition->getLabel(),
          '#state_machine_transition' => $transition->getId(),
        ];
      }
    }

    (new CacheableMetadata())
      ->addCacheableDependency($this->announcement)
      ->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $is_new = $this->announcement->isNew();
    $initial_state = $this->announcement->getWorkflowStateInstance();

    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#type'] === 'submit' && isset($trigger['#state_machine_transition'])) {
      $this->announcement->getWorkflowStateField()->applyTransitionById($trigger['#state_machine_transition']);
    }
    $this->announcement
      ->set('subject', $form_state->getValue('subject'))
      ->set('body', $form_state->getValue('body'))
      ->save();

    $args = [
      '@to' => $this->announcement->getWorkflowStateInstance()->getLabel(),
      '%group' => $this->announcement->getGroup()->label(),
      '@type' => $this->announcement->getGroup()->get('rid')->entity->getSingularLabel(),
    ];

    if ($is_new) {
      $status = $this->t("The announcement has been created as @to. Review the announcement and, when is ready to be sent, use the 'Send' button.", $args);
    }
    elseif ($initial_state->getId() == $this->announcement->getWorkflowState()) {
      $status = $this->t("The announcement has been updated but it's still in @to state. Review the announcement and, when is ready to be sent, use the 'Send' button.", $args);
    }
    elseif ($initial_state->getId() === 'draft' && $this->announcement->getWorkflowState() === 'sent') {
      $status = $this->t("Your announcement has been sent for validation by Joinup moderators. Once approved, will reach the %group @type members.", $args);
      $redirect = Url::fromRoute('view.announcements.group', [
        'rdf_entity' => $this->announcement->getGroupId(),
      ]);
    }
    elseif ($this->announcement->getWorkflowState() === 'rejected') {
      $status = $this->t("The announcement has been rejected. Press the 'Undo' button to restore the announcement.");
    }
    elseif ($initial_state->getId() === 'rejected') {
      $status = $this->t("The announcement has been restored.");
    }
    elseif ($this->announcement->getWorkflowState() === 'delivered') {
      $status = $this->t("The announcement has been delivered to the %group @type members.", $args);
      $redirect = Url::fromRoute('view.announcements.group', [
        'rdf_entity' => $this->announcement->getGroupId(),
      ]);
    }

    if (isset($status)) {
      $this->messenger()->addStatus($status);
    }

    if (!isset($redirect)) {
      $redirect = Url::fromRoute('entity.rdf_entity.announcement_edit', [
        'rdf_entity' => $this->announcement->getGroupId(),
        'announcement' => $this->announcement->id(),
      ]);
    }
    $form_state->setRedirectUrl($redirect);
  }

}
