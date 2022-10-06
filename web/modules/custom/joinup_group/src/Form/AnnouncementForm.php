<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Form;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
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
   * The message delivery service.
   */
  protected JoinupMessageDeliveryInterface $messageDelivery;

  /**
   * Builds a new form instance.
   *
   * @param \Drupal\joinup_notification\JoinupMessageDeliveryInterface $message_delivery
   *   The message delivery service.
   */
  public function __construct(JoinupMessageDeliveryInterface $message_delivery) {
    $this->messageDelivery = $message_delivery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('joinup_notification.message_delivery'));
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
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'text_format',
      '#format' => $this->announcement->getBodyFormat(),
      '#title' => $this->t('Body'),
      '#default_value' => $this->announcement->getBody(),
      '#required' => TRUE,
      '#allowed_formats' => ['minimal'],
    ];

    $arguments = $this->getArguments();
    $form['arguments'] = [
      '#theme' => 'item_list',
      '#items' => array_map(function (string $token) use ($arguments): MarkupInterface {
        return $this->t('%token - @label', [
          '%token' => $token,
          '@label' => $arguments[$token]['label'],
        ]);
      }, array_keys($arguments)),
      '#title' => $this->t('The following placeholders are available to be used in the message body:'),
    ];

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

      $form['actions']['test'] = [
        '#type' => 'submit',
        '#value' => $this->t('Test'),
        '#test_announcement' => TRUE,
      ];
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
      ->setSubject($form_state->getValue('subject'))
      ->setBody($form_state->getValue(['body', 'value']))
      ->setArguments(array_map(function (array $argument): string {
        return (string) $argument['value'];
      }, $this->getArguments()))
      ->save();

    $args = [
      '@to' => $this->announcement->getWorkflowStateInstance()->getLabel(),
      '%group' => $this->announcement->getGroup()->label(),
      '@type' => $this->announcement->getGroup()->get('rid')->entity->getSingularLabel(),
    ];

    if ($trigger['#type'] === 'submit' && !empty($trigger['#test_announcement'])) {
      $email = $this->currentUser()->getEmail();
      $this->messageDelivery->sendMessageToEmailAddresses($this->announcement, [
        $email,
      ]);
      $status = $this->t('A sample announcement has been delivered to %email.', [
        '%email' => $email,
      ]);
    }
    elseif ($is_new) {
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
      $status = $this->t("The announcement has been rejected. Press the 'Undo reject' button to restore the announcement.");
    }
    elseif ($initial_state->getId() === 'rejected') {
      $status = $this->t("The announcement has been restored.");
    }
    elseif ($this->announcement->getWorkflowState() === 'delivered') {
      if ($this->announcement->getGroup()->getMemberCount() > 50) {
        $status = $this->t("The announcement has been scheduled to be delivered to the %group @type members. Because the @type has a large amount of members the emails will be sent in several batches.", $args);
      }
      else {
        $status = $this->t("The announcement has been delivered to the %group @type members.", $args);
      }
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

  /**
   * Builds a list of possible arguments.
   *
   * @return array[]
   *   An associative array keyed by the argument placeholder and having an
   *   associative array as values, each with two keys:
   *   - label: A short definition of the argument.
   *   - value: The argument value.
   */
  protected function getArguments(): array {
    $group = $this->announcement->getGroup();
    $sender = $this->announcement->getOwner();
    $vars = ['@label' => $group->get('rid')->entity->getSingularLabel()];

    return [
      '@group:name' => [
        'label' => $this->t('The @label title', $vars),
        'value' => $group->label(),
      ],
      '@group:type' => [
        'label' => $this->t('The group type (collection, solution)', $vars),
        'value' => $vars['@label'],
      ],
      '@group:url' => [
        'label' => $this->t('The @label landing page URL', $vars),
        'value' => $group->toUrl()->setAbsolute()->toString(),
      ],
      '@sender:name' => [
        'label' => $this->t('The announcement sender name'),
        'value' => $sender->getDisplayName(),
      ],
      '@sender:url' => [
        'label' => $this->t('The announcement sender profile URL'),
        'value' => $sender->toUrl()->setAbsolute()->toString(),
      ],
    ];
  }

}
