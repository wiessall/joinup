<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Form;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Form\JoinGroupFormBase;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A simple form with a button to join or leave a solution.
 */
class JoinSolutionForm extends JoinGroupFormBase {

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    MembershipManagerInterface $membershipManager,
    AccessManagerInterface $accessManager,
    MessengerInterface $messenger,
    FormBuilderInterface $formBuilder,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($entityTypeManager, $membershipManager, $accessManager, $messenger, $formBuilder);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
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
  public function buildForm(array $form, FormStateInterface $form_state, ?AccountProxyInterface $user = NULL, ?GroupInterface $group = NULL): array {
    $form = parent::buildForm($form, $form_state, $user, $group);

    // @todo Move this into the joinup_subscription module to solve a circular
    //   dependency.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6330
    $operation = $form['join']['#name'] ?? NULL;
    if ($operation === 'join') {
      // Show the subscription dialog in a modal on join.
      $form['join']['#ajax'] = ['callback' => '::showSubscribeDialog'];
      $form['#attached']['drupalSettings']['joinGroupData'] = [
        'id' => $group->id(),
      ];
    }

    // Rebuild button for refreshing the form if membership is created by the
    // SubscribeToSolutionForm.
    $form['rebuild'] = [
      '#type' => 'button',
      '#value' => $this->t('Rebuild'),
      '#attributes' => ['class' => ['visually-hidden']],
      '#name' => 'join_solution_rebuild',
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('joinup_solution.subscribe', ['node' => $this->group->id()]);
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
      $modal_form = $this->formBuilder->getForm(SubscribeToSolutionForm::class, $this->group);
      $modal_form['#attached']['library'][] = 'core/drupal.dialog.ajax';

      // Allow modules to alter or replace the Ajax command.
      $event = new AjaxCommandEvent(new OpenModalDialogCommand(SubscribeToSolutionForm::title($this->group), $modal_form, [
        'width' => '500',
      ]), [
        'instance' => 'join_solution.subscribe_dialog.open',
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
   * Returns the label for the submit button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the label.
   */
  public function getJoinSubmitLabel(): TranslatableMarkup {
    return $this->t('Subscribe to this :type', [
      ':type' => $this->group->bundle(),
    ]);
  }

  /**
   * Returns the label for the leave submit button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the label.
   */
  public function getLeaveSubmitLabel(): TranslatableMarkup {
    $membership = $this->membershipManager->getMembership($this->group, $this->user->id(), OgMembershipInterface::ALL_STATES);
    if ($membership->hasRole($this->group->getEntityTypeId() . '-' . $this->group->bundle() . '-facilitator') || $membership->hasRole($this->group->getEntityTypeId() . '-' . $this->group->bundle() . '-author')) {
      return $this->t('Leave this :type', [
        ':type' => $this->group->bundle(),
      ]);
    }

    return $this->t('Unsubscribe');
  }

  /**
   * {@inheritdoc}
   */
  protected function createMembership(string $state, array $roles, UserInterface $user, GroupInterface $group): OgMembershipInterface {
    $membership = parent::createMembership($state, $roles, $user, $group);

    $bundles = [];
    foreach (CommunityContentHelper::BUNDLES as $bundle) {
      $bundles[] = ['entity_type' => 'node', 'bundle' => $bundle];
    }

    $membership->set('subscription_bundles', $bundles);

    return $membership;
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
