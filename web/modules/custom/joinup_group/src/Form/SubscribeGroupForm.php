<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\collection\Entity\CodeOfConductMembershipInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Traits\MembershipCreateTrait;
use Drupal\joinup_subscription\JoinupSubscriptionsHelper;
use Drupal\node\NodeInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A form suggesting the user to subscribe to a collection after joining.
 *
 * This form is shown in a modal dialog after the user joins a collection.
 *
 * @todo Move this into the joinup_subscription module to solve a circular
 *   dependency.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6330
 * @see \Drupal\collection\Form\JoinCollectionForm::showSubscribeDialog()
 */
class SubscribeGroupForm extends FormBase {

  use MembershipCreateTrait;
  use AutowireTrait;

  protected const string COC_CHECKBOX_KEY = 'code_of_conduct_agreed';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'og.membership_manager')]
    protected readonly MembershipManagerInterface $membershipManager,
    #[Autowire(service: 'messenger')]
    protected $messenger,
    protected readonly FormBuilderInterface $formBuilder,
    protected readonly TimeInterface $time,
    protected readonly EventDispatcherInterface $eventDispatcher,
    protected readonly PathValidatorInterface $pathValidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'subscribe_group_form';
  }

  /**
   * Title callback of the form.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $node
   *   The collection to join or subscribe to.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The title.
   */
  public static function title(GroupInterface $node): TranslatableMarkup|string {
    return t('Welcome to @title', ['@title' => $node->label()]);
  }

  /**
   * Returns the destination path if it is a valid internal route.
   *
   * @return string|null
   *   The destination path if exists and valid, NULL otherwise.
   */
  protected function getRedirectPath(): ?string {
    $request = $this->getRequest();
    $redirectPath = FALSE;
    $rawDestination = $request->query->get('destination', '');
    if (!$rawDestination) {
      return NULL;
    }
    $options = UrlHelper::parse($rawDestination);
    try {
      $redirectUrl = Url::fromUserInput('/' . ltrim($options['path'], '/'), $options);
      $redirectPath = $redirectUrl->isRouted()
        ? $redirectUrl->toString()
        : FALSE;
    }
    catch (\InvalidArgumentException) {
    }
    return $redirectPath ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $node = NULL): array {
    $coc_components = $this->getCodeOfConductComponents($node);
    $membership = $this->getUserNonBlockedMembership($node);
    $intro_message = $membership && $membership->isPending() ?
      $this->t('When your membership is approved you will be able to publish content in it.') :
      $this->t("You're now an active member of this space and ready to participate fully!");
    $form = $coc_components ?? [
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $intro_message,
        '#weight' => -3,
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('You can update your notification preferences from your profile page at any time.'),
      ],
    ];

    $form['subscribe'] = [
      '#type' => 'checkbox',
      '#title' => $this->t(
        'Notify me when content is published in <a href=":parent-url" target="_blank">@parent-name</a>.',
        [
          ':parent-url' => $node->toUrl()->toString(),
          '@parent-name' => $node->label(),
        ]
      ),
      '#weight' => -2,
    ];

    $form['redirect'] = [
      '#type' => 'hidden',
      '#default_value' => $form_state->getUserInput()['redirect'] ?? $this->getRedirectPath(),
    ];

    // This form should only be accessible if the user is already a member of
    // the collection.
    $form['#access'] = $this->access($node)->isAllowed();
    // Keep track of the collection we are subscribing to.
    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $node->id(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // This form can be opened via AJAX in a modal dialog that is triggered by
    // submitting the JoinCollectionForm. By default, Drupal will send the
    // AJAX requests to the route of the original form, which in this case
    // would be the JoinCollectionForm. We need to manually specify the
    // callback URL and the query argument that triggers AJAX so that this
    // form will be loaded.
    // @see https://www.drupal.org/project/drupal/issues/2934463
    $ajax_callback = [
      'callback' => '::confirmSubscription',
      'url' => $node->toUrl('subscribe'),
      'options' => [
        'query' => [
          FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
        ],
      ],
    ];

    $form['actions']['confirm_subscribe'] = [
      '#value' => $this->t('Confirm'),
      '#op' => 'subscribe',
      '#type' => 'submit',
      '#ajax' => $ajax_callback,
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#extra_suggestion' => 'light_blue',
      '#weight' => 10,
      '#value' => $this->t('Cancel'),
      '#op' => 'cancel',
      '#executes_submit_callback' => TRUE,
      '#ajax' => $ajax_callback,
    ];

    return $form;
  }

  /**
   * Adds the necessary Code of Conduct form elements to the form.
   *
   * @param \Drupal\node\NodeInterface|null $collection
   *   The collection.
   *
   * @return array[]|null
   *   The form components about accepting the Code of Conduct document, or
   *   NULL if there is nothing what users should agree to.
   */
  protected function getCodeOfConductComponents(?NodeInterface $collection): ?array {
    if (
      !$collection instanceof CollectionInterface ||
      !$collection->hasCodeOfConductToAccept()
    ) {
      return NULL;
    }

    $codeOfConduct = $collection->getCodeOfConduct();
    $codeOfConductArgs = [
      ':code-of-conduct-url' => $codeOfConduct->toUrl()->toString(),
      '@group-label' => $collection->label(),
    ];

    $codeOfConductComponents['code_of_conduct_lead'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'To validate your membership and send it for approval, please tick the box agreeing with the <a href=":code-of-conduct-url" target="_blank">Code of Conduct</a>. This helps us maintain a safe and respectful environment for all members of @group-label.',
        $codeOfConductArgs,
      ),
      '#weight' => -5,
    ];
    $codeOfConductComponents[static::COC_CHECKBOX_KEY] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and agree to the <a href=":code-of-conduct-url" target="_blank">Code of Conduct</a> of @group-label.', $codeOfConductArgs),
      '#return_value' => $codeOfConduct->getRevisionId(),
      '#required' => TRUE,
      '#required_error' => $this->t('You must agree to the <a href=":code-of-conduct-url">Code of Conduct</a> before asking for membership.', $codeOfConductArgs),
      '#weight' => -3,
    ];

    $codeOfConductComponents['code_of_conduct_trailer'] = [
      '#type' => 'item',
      '#markup' => $this->t('You will receive a confirmation email once your membership has been approved.'),
      '#weight' => -1,
    ];

    return $codeOfConductComponents;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $operation = $form_state->getTriggeringElement()['#op'] ?? NULL;
    if ($operation === 'cancel') {
      $form_state->clearErrors();
      return;
    }
    parent::validateForm($form, $form_state);

    $node = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('entity_id'));
    if ($node instanceof CollectionInterface) {
      $membership = $this->getUserNonBlockedMembership($node);
      if (!$membership && !isset($form[static::COC_CHECKBOX_KEY])) {
        $form_state->setErrorByName('collection', $this->t('Please join the collection before subscribing to it.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $group = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('entity_id'));
    $subscribe = (bool) $form_state->getValue('subscribe');
    if ($group instanceof CollectionInterface) {
      if (!$this->isAjax()) {
        $redirectValue = $form_state->getValue('redirect');
        $redirect = $redirectValue
          ? $this->pathValidator->getUrlIfValid($redirectValue)
          : NULL;
        $form_state->setRedirectUrl($redirect ?: $group->toUrl());
      }
      $operation = $form_state->getTriggeringElement()['#op'] ?? NULL;
      if ($operation === 'cancel') {
        return;
      }
      $parameters = ['%collection' => $group->label()];
      $codeOfConductHasBeenApproved = !empty($form_state->getValue(static::COC_CHECKBOX_KEY));

      $membership = $this->getUserNonBlockedMembership($group);
      $noInitialMembership = empty($membership);
      if ($noInitialMembership && $codeOfConductHasBeenApproved) {
        $state = $group->isClosed() ? OgMembershipInterface::STATE_PENDING
          : OgMembershipInterface::STATE_ACTIVE;
        $membership = $this->createSaveNewMembership($state, $this->getUser(), $group);
      }

      if ($noInitialMembership && $membership) {
        $message = isset($state) && $state === OgMembershipInterface::STATE_PENDING
          ? $this->t('You will get a notification once your membership at %collection is approved.', $parameters)
          : $this->t('You have been joined to %collection.', $parameters);
        $this->messenger()->addStatus($message);
      }

      if ($membership instanceof CodeOfConductMembershipInterface &&
        $codeOfConduct = $group->getCodeOfConduct()) {
        $membership
          ->grantFullMembershipWithCocAcceptance($codeOfConduct)
          ->save();
      }

      if ($subscribe && $membership instanceof OgMembershipInterface) {
        $membership = $this->subscribe($membership);
        $message = $membership->isPending() ?
          $this->t('You have been subscribed to %collection and will receive weekly notifications once your membership is approved.', $parameters) :
          $this->t('You have been subscribed to %collection and will receive weekly notifications. To manage your notifications go to <em>My subscriptions</em> in your user menu.', $parameters);
        $this->messenger()->addStatus($message);
      }
    }
    else {
      $operation = $form_state->getTriggeringElement()['#op'] ?? NULL;
      if ($operation === 'cancel') {
        return;
      }

      $membership = $this->createSaveNewMembership(OgMembershipInterface::STATE_ACTIVE, $this->getUser(), $group);
      if ($subscribe) {
        $membership = $this->subscribe($membership);
      }
      $this->messenger->addStatus($group->getNewMembershipSuccessMessage($membership));
    }
  }

  /**
   * Subscribes collection or solution membership.
   *
   * @param \Drupal\og\OgMembershipInterface $membership
   *   Membership param.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function subscribe(OgMembershipInterface $membership): OgMembershipInterface {
    $groupBundle = $membership->getGroupBundle();
    if ($groupBundle == 'collection') {
      $subscription_bundles = JoinupSubscriptionsHelper::provideDataForField($groupBundle);
    }
    else {
      $subscription_bundles = [];
      foreach (CommunityContentHelper::BUNDLES as $bundle) {
        $subscription_bundles[] = ['entity_type' => 'node', 'bundle' => $bundle];
      }
    }
    // Remove the 'assessment_report' subscription for all collections,
    // except the assessment collection.
    if ($membership->getGroup()->uuid() !== AssessmentInterface::GROUP_UUID) {
      $subscription_bundles = array_values(array_filter($subscription_bundles, function ($bundle) {
        return $bundle['bundle'] !== 'assessment_report';
      }));
    }
    $membership->set('subscription_bundles', $subscription_bundles);
    $membership->save();
    return $membership;
  }

  /**
   * AJAX callback showing a confirmation after joining, and closing the modal.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function confirmSubscription(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Output messages in the page.
    $messages = ['#type' => 'status_messages'];

    if (!$form_state->getErrors()) {
      // Allow modules to alter or replace the Ajax command.
      $event = new AjaxCommandEvent(new CloseModalDialogCommand(), [
        'instance' => 'join_group.subscribe_dialog.close',
      ]);
      $this->eventDispatcher->dispatch($event);

      $response->addCommand($event->getCommand());
      $response->addCommand(new AfterCommand('*[data-drupal-messages-fallback]', $messages));
      $response->addCommand(new InvokeCommand('[name="join_group_form_base_rebuild"]', 'trigger', ['mousedown']));
    }
    else {
      $response->addCommand(new RemoveCommand('#drupal-modal form > [data-drupal-messages], #drupal-modal form > [data-drupal-messages-fallback]'));
      $response->addCommand(new PrependCommand('#drupal-modal form', $messages));
    }
    return $response;
  }

  /**
   * AJAX callback showing a confirmation after joining, and closing the modal.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function confirmJoin(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Output messages in the page.
    $messages = ['#type' => 'status_messages'];
    $response->addCommand(new AfterCommand('*[data-drupal-messages-fallback]', $messages));

    if (!$form_state->getErrors()) {
      // Allow modules to alter or replace the Ajax command.
      $event = new AjaxCommandEvent(new CloseModalDialogCommand(), [
        'instance' => 'join_collection.subscribe_dialog.close',
      ]);
      $this->eventDispatcher->dispatch($event);

      $response->addCommand($event->getCommand());
    }
    return $response;
  }

  /**
   * Access check for the form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The collection for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    $user = $this->getUser();
    if ($node instanceof CollectionInterface) {
      $hasCodeOfConductToAccept = $node instanceof CollectionInterface && $node->hasCodeOfConductToAccept();
      return AccessResult::allowedIf(
        $user->isAuthenticated() &&
        ($this->getUserNonBlockedMembership($node) || $hasCodeOfConductToAccept)
      );
    }
    return AccessResult::allowedIf($user->isAuthenticated());
  }

  /**
   * Returns a membership of the user that is active or pending.
   *
   * @param \Drupal\node\NodeInterface $collection
   *   The group entity.
   *
   * @return \Drupal\og\OgMembershipInterface|null
   *   The membership of the user or null.
   */
  protected function getUserNonBlockedMembership(NodeInterface $collection): ?OgMembershipInterface {
    return $this->membershipManager->getMembership($collection, $this->getUser()->id(), [
      OgMembershipInterface::STATE_ACTIVE,
      OgMembershipInterface::STATE_PENDING,
    ]);
  }

  /**
   * Returns the full user entity object for the current user.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  protected function getUser(): UserInterface {
    return $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());
  }

  /**
   * Determines if the current request is via AJAX.
   *
   * @return bool
   *   TRUE if the current request is via AJAX, FALSE otherwise.
   */
  protected function isAjax(): bool {
    $wrapper_format = $this->getRequestWrapperFormat() ?? '';
    return str_contains($wrapper_format, 'drupal_ajax') ||
      str_contains($wrapper_format, 'drupal_modal') ||
      str_contains($wrapper_format, 'drupal_dialog');
  }

  /**
   * Gets the wrapper format of the current request.
   *
   * @return string
   *   The wrapper format. Empty string if the wrapper format is not set.
   */
  protected function getRequestWrapperFormat(): string {
    return $this->getRequest()->query->get(MainContentViewSubscriber::WRAPPER_FORMAT, '');
  }

}
