<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\collection\Entity\Collection;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Traits\MembershipCreateTrait;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A simple form with a button to join or leave a collection.
 */
abstract class JoinGroupFormBase extends FormBase implements JoinGroupFormInterface {

  use MembershipCreateTrait;

  /**
   * The group to join.
   *
   * @var \Drupal\joinup_group\Entity\GroupInterface
   */
  protected GroupInterface $group;

  /**
   * The current user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'join_group_form';
  }

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MembershipManagerInterface $membershipManager,
    protected AccessManagerInterface $accessManager,
    MessengerInterface $messenger,
    protected FormBuilderInterface $formBuilder,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('og.membership_manager'),
      $container->get('access_manager'),
      $container->get('messenger'),
      $container->get('form_builder'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getJoinSubmitLabel(): TranslatableMarkup {
    return $this->t('Join this :type', [
      ':type' => $this->group->bundle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getLeaveSubmitLabel(): TranslatableMarkup {
    return $this->t('Leave this :type', [
      ':type' => $this->group->bundle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): GroupInterface {
    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AccountProxyInterface $user = NULL, ?GroupInterface $group = NULL): array {
    assert($group instanceof GroupInterface, 'Only groups can be joined.');

    $form['#access'] = $this->access();
    $this->group = $group;
    $this->user = $this->loadUser((int) $user->id());

    $membership = $this->user->isAnonymous() ? NULL : $this->getUserNonBlockedMembership();
    $parameters = ['node' => $this->group->id()];

    // User should not be allowed to subscribe if entity it archived.
    if (empty($membership) && $group instanceof ArchivableEntityInterface && $group->isArchived()) {
      $form['#access'] = FALSE;
    }

    // Inform anonymous users that they need to authenticate to join the group.
    elseif ($this->user->isAnonymous()) {
      if ($this->accessManager->checkNamedRoute('joinup_group.authenticate_to_join', $parameters)) {
        $form['authenticate'] = [
          '#type' => 'link',
          '#title' => $this->getJoinSubmitLabel(),
          '#url' => Url::fromRoute('joinup_group.authenticate_to_join', $parameters),
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode([
              'width' => 'auto',
            ]),
          ],
        ];
        $form['#attached']['library'][] = 'core/drupal.ajax';
      }
    }

    // In case the user is not a member or does not have a pending membership,
    // give the possibility to request one.
    elseif (empty($membership)) {
      $form['join'] = [
        '#type' => 'submit',
        '#value' => $this->getJoinSubmitLabel(),
        '#name' => 'join',
      ];
    }

    // If the user is already a member of the group, show a link to the
    // membership cancellation confirmation form, disguised as a form submit
    // button. The confirmation form should open in a modal dialog for
    // JavaScript-enabled browsers.
    elseif ($membership->getState() === OgMembershipInterface::STATE_ACTIVE) {
      $form['leave'] = [
        '#theme' => 'group_leave_button',
      ];
      if ($this->accessManager->checkNamedRoute('joinup_group.leave_confirm_form', $parameters)) {
        $form['leave']['#confirm'] = [
          '#type' => 'link',
          '#title' => $this->getLeaveSubmitLabel(),
          '#url' => Url::fromRoute('joinup_group.leave_confirm_form', $parameters),
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['width' => 'auto']),
          ],
        ];
      }
      $form['#attached']['library'][] = 'core/drupal.ajax';
    }

    // If the user has a pending membership, do not allow to request a new one.
    elseif ($membership->getState() === OgMembershipInterface::STATE_PENDING) {
      $form['pending'] = [
        '#type' => 'link',
        '#title' => $this->t('Membership is pending'),
        '#url' => Url::fromRoute('<current>', [], ['fragment' => 'pending']),
      ];
    }

    $operation = $form['join']['#name'] ?? NULL;
    if ($operation === 'join') {
      // Show the subscription dialog in a modal on join.
      $form['join']['#ajax'] = ['callback' => '::showSubscribeDialog'];
      // Add library and the needed settings which opens the subscribe dialog if
      // the user has just authenticated after indicating their desire to join.
      $form['#attached']['library'][] = 'collection/subscribe_dialog';
      $form['#attached']['drupalSettings']['joinGroupData'] = [
        'id' => $group->id(),
      ];
    }

    // Rebuild button for refreshing the form if membership is created by the
    // SubscribeToCollectionForm.
    $form['rebuild'] = [
      '#type' => 'button',
      '#value' => $this->t('Rebuild'),
      '#attributes' => ['class' => ['visually-hidden']],
      '#name' => 'join_group_form_base_rebuild',
      '#ajax' => [
        'callback' => '::rebuild',
      ],
      '#limit_validation_errors' => [],
    ];
    $form['#prefix'] = "<div id=\"{$this->ajaxWrapperId()}\">";
    $form['#suffix'] = "</div>";

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Only authenticated users can join a group.
    if ($this->user->isAnonymous()) {
      $form_state->setErrorByName('user', $this->t('<a href=":login">Sign in</a> or <a href=":register">register</a> to change your group membership.', [
        ':login' => Url::fromRoute('user.login')->toString(),
        ':register' => Url::fromRoute('user.register')->toString(),
      ]));
    }

    $membership = $this->getUserNonBlockedMembership();
    // Make sure the user is not already a member.
    if (!empty($membership)) {
      $form_state->setErrorByName('group', $this->t('You already are a member of this group.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirectUrl(
      $this->group->toUrl('subscribe')
        ->setOption('query', [
          'destination' => Url::fromRoute('<current>')->toString(),
        ])
    );
    if ($this->group instanceof Collection) {
      if ($this->group->hasCodeOfConductToAccept()) {
        // Membership is created only after user accepted the code of conduct
        // document.
        return;
      }
      $state = $this->group->isClosed()
        ? OgMembershipInterface::STATE_PENDING
        : OgMembershipInterface::STATE_ACTIVE;
      $membership = $this->createSaveNewMembership($state, $this->user, $this->group);

      $this->messenger->addStatus($this->group->getNewMembershipSuccessMessage($membership));
    }
  }

  /**
   * AJAX callback showing a form to subscribe to the collection after joining.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function showSubscribeDialog(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Output messages in the page.
    $messages = ['#type' => 'status_messages'];
    $response->addCommand(new AfterCommand('*[data-drupal-messages-fallback]', $messages));

    // If the form submitted successfully, make an offer the user cannot refuse.
    if (!$form_state->getErrors()) {
      // Rebuild the form and replace it in the page, so that the "Join this
      // "collection" button will be replaced with either the "You're a member"
      // button or the "Membership is pending" button.
      $rebuiltForm = $this->formBuilder->rebuildForm($this->getFormId(), $form_state, $form);
      $response->addCommand(new ReplaceCommand("#{$this->ajaxWrapperId()}", $rebuiltForm));
      $modal_form = $this->formBuilder->getForm(SubscribeGroupFormBase::class, $this->group);
      $modal_form['#attached']['library'][] = 'core/drupal.dialog.ajax';

      // Allow modules to alter or replace the Ajax command.
      $event = new AjaxCommandEvent(new OpenModalDialogCommand(SubscribeGroupFormBase::title($this->group), $modal_form, [
        'width' => '500',
      ]), [
        'instance' => 'join_group.subscribe_dialog.open',
        'content' => $modal_form,
      ]);
      $this->eventDispatcher->dispatch($event);
      $response->addCommand($event->getCommand())
        ->addAttachments($event->getAttachments());
    }
    return $response;
  }

  /**
   * AJAX callback to reset the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function rebuild(array $form, FormStateInterface $formState): AjaxResponse {
    $rebuiltForm = $this->formBuilder->rebuildForm($this->getFormId(), $formState, $form);
    return (new AjaxResponse())
      ->addCommand(new ReplaceCommand("#{$this->ajaxWrapperId()}", $rebuiltForm));
  }

  /**
   * Access check for the form.
   *
   * @return bool
   *   True if the form can be accessed, false otherwise.
   */
  public function access(): bool {
    return $this->getRouteMatch()->getRouteName() !== 'joinup_group.leave_confirm_form';
  }

  /**
   * Returns a membership of the user that is active or pending.
   *
   * @return \Drupal\og\OgMembershipInterface|null
   *   The membership of the user or null.
   */
  protected function getUserNonBlockedMembership(): ?OgMembershipInterface {
    return $this->membershipManager->getMembership($this->group, $this->user->id(), [
      OgMembershipInterface::STATE_ACTIVE,
      OgMembershipInterface::STATE_PENDING,
    ]);
  }

  /**
   * Loads the user with the given ID.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  protected function loadUser(int $user_id): UserInterface {
    return $this->entityTypeManager->getStorage('user')->load($user_id);
  }

  /**
   * Returns the HTML ID of the form wrapper.
   *
   * @return string
   *   The HTML ID of the form wrapper.
   */
  protected function ajaxWrapperId(): string {
    return $this->getFormId() . '_ajax_wrapper';
  }

}
