<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

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
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Traits\MembershipCreateTrait;
use Drupal\joinup_subscription\JoinupSubscriptionsHelper;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\user\UserInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class SubscribeToCollectionForm extends FormBase {

  use MembershipCreateTrait;

  protected const string COC_CHECKBOX_KEY = 'code_of_conduct_agreed';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MembershipManagerInterface $membershipManager,
    MessengerInterface $messenger,
    protected FormBuilderInterface $formBuilder,
    protected TimeInterface $time,
    protected EventDispatcherInterface $eventDispatcher,
    protected PathValidatorInterface $pathValidator,
  ) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('og.membership_manager'),
      $container->get('messenger'),
      $container->get('form_builder'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher'),
      $container->get('path.validator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'subscribe_to_collection_form';
  }

  /**
   * Title callback of the form.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The collection to join or subscribe to.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The title.
   */
  public static function title(GroupInterface $rdf_entity): TranslatableMarkup|string {
    return t('Welcome to @title', ['@title' => $rdf_entity->label()]);
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
  public function buildForm(array $form, FormStateInterface $form_state, ?RdfInterface $rdf_entity = NULL): array {
    $coc_components = $this->getCodeOfConductComponents($rdf_entity);
    $membership = $this->getUserNonBlockedMembership($rdf_entity);
    $intro_message = $membership && $membership->isPending() ?
      $this->t('When your membership is approved you will be able to publish content in it.') :
      $this->t('You have joined the collection and you are now able to publish content in it.');
    $form = $coc_components ?? [
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $intro_message,
        '#weight' => -3,
      ],
    ];

    $form['subscribe'] = [
      '#type' => 'checkbox',
      '#title' => $this->t(
        'Notify me when content is published in <a href=":parent-url" target="_blank">@parent-name</a>.',
        [
          ':parent-url' => $rdf_entity->toUrl()->toString(),
          '@parent-name' => $rdf_entity->label(),
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
    $form['#access'] = $this->access($rdf_entity)->isAllowed();
    // Keep track of the collection we are subscribing to.
    $form['collection_id'] = [
      '#type' => 'value',
      '#value' => $rdf_entity->id(),
    ];

    $form['proposal'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#attributes' => [
        'class' => ['fs-6'],
      ],
      '#value' => $this->t('Want to subscribe for new content notifications, too?'),
    ];
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('You can update your notification preferences from your profile page at any time.'),
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
      'url' => $rdf_entity->toUrl('subscribe'),
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
   * Adds the necessary code of conduct form elements to the form.
   *
   * @param \Drupal\rdf_entity\RdfInterface|null $collection
   *   The collection.
   *
   * @return array[]|null
   *   The form components about accepting the Code of Conduct document, or
   *   NULL if there is nothing what users should agree to.
   */
  protected function getCodeOfConductComponents(?RdfInterface $collection): ?array {
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
        'To complete your membership, we need to make sure you agree with our <a href=:code-of-conduct-url target="_blank">Code of conduct</a>. This helps us maintain a safe and respectful environment for all members of @group-label',
        $codeOfConductArgs,
      ),
      '#weight' => -5,
    ];
    $codeOfConductComponents[static::COC_CHECKBOX_KEY] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and agree to the <a href=:code-of-conduct-url target="_blank">Code of conduct</a> of @group-label.', $codeOfConductArgs),
      '#return_value' => $codeOfConduct->getRevisionId(),
      '#required' => TRUE,
      '#required_error' => $this->t('You must agree to the <a href=:code-of-conduct-url>Code of conduct</a> before asking for membership.', $codeOfConductArgs),
      '#weight' => -3,
    ];

    $codeOfConductComponents['code_of_conduct_trailer'] = [
      '#type' => 'item',
      '#markup' => $this->t('You will receive a confirmation email once your application has been approved. We look forward to having you as a valued member of our community!<br><br>If you have any questions or need assistance, please feel free to contact us.'),
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

    $collection = $this->loadCollection($form_state->getValue('collection_id'));

    // The user should be a non-blocked member of the collection.
    $membership = $this->getUserNonBlockedMembership($collection);
    if (!$membership && !isset($form[static::COC_CHECKBOX_KEY])) {
      $form_state->setErrorByName('collection', $this->t('Please join the collection before subscribing to it.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $collection = $this->loadCollection($form_state->getValue('collection_id'));
    if (!$this->isAjax()) {
      $redirectValue = $form_state->getValue('redirect');
      $redirect = $redirectValue
        ? $this->pathValidator->getUrlIfValid($redirectValue)
        : NULL;
      $form_state->setRedirectUrl($redirect ?: $collection->toUrl());
    }
    $operation = $form_state->getTriggeringElement()['#op'] ?? NULL;
    if ($operation === 'cancel') {
      return;
    }
    $parameters = ['%collection' => $collection->getName()];
    $codeOfConductHasBeenApproved = !empty($form_state->getValue(static::COC_CHECKBOX_KEY));

    $membership = $this->getUserNonBlockedMembership($collection);
    $noInitialMembership = empty($membership);
    if ($noInitialMembership && $codeOfConductHasBeenApproved) {
      $state = $collection->isClosed() ? OgMembershipInterface::STATE_PENDING
        : OgMembershipInterface::STATE_ACTIVE;
      $membership = $this->createSaveNewMembership($state, $this->getUser(), $collection);
    }

    if ($noInitialMembership && $membership) {
      $message = isset($state) && $state === OgMembershipInterface::STATE_PENDING
        ? $this->t('You will get a notification once your membership at %collection is approved.', $parameters)
        : $this->t('You have been joined to %collection.', $parameters);
      $this->messenger()->addStatus($message);
    }

    $subscribe = (bool) $form_state->getValue('subscribe');
    if ($subscribe) {
      $subscription_bundles = JoinupSubscriptionsHelper::provideDataForField('collection');
      $membership
        ->set('announcements', TRUE)
        ->set('subscription_bundles', $subscription_bundles)
        ->save();

      // Check if the user is pending, so we can adapt the message for the user.
      $is_pending = !empty($membership) && $membership->isPending();

      $message = $is_pending ?
        $this->t('You have been subscribed to %collection and will receive weekly notifications once your membership is approved.', $parameters) :
        $this->t('You have been subscribed to %collection and will receive weekly notifications. To manage your notifications go to <em>My subscriptions</em> in your user menu.', $parameters);
      $this->messenger()->addStatus($message);
    }
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
        'instance' => 'join_collection.subscribe_dialog.close',
      ]);
      $this->eventDispatcher->dispatch($event);

      $response->addCommand($event->getCommand());
      $response->addCommand(new AfterCommand('*[data-drupal-messages-fallback]', $messages));
      $response->addCommand(new InvokeCommand('[name="join_collection_rebuild"]', 'trigger', ['mousedown']));
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
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RdfInterface $rdf_entity): AccessResultInterface {
    $user = $this->getUser();
    $hasCodeOfConductToAccept = $rdf_entity instanceof CollectionInterface && $rdf_entity->hasCodeOfConductToAccept();
    return AccessResult::allowedIf(
      $user->isAuthenticated() &&
      ($this->getUserNonBlockedMembership($rdf_entity) || $hasCodeOfConductToAccept)
    );
  }

  /**
   * Returns a membership of the user that is active or pending.
   *
   * @param \Drupal\rdf_entity\RdfInterface $collection
   *   The group entity.
   *
   * @return \Drupal\og\OgMembershipInterface|null
   *   The membership of the user or null.
   */
  protected function getUserNonBlockedMembership(RdfInterface $collection): ?OgMembershipInterface {
    return $this->membershipManager->getMembership($collection, $this->getUser()->id(), [
      OgMembershipInterface::STATE_ACTIVE,
      OgMembershipInterface::STATE_PENDING,
    ]);
  }

  /**
   * Loads the collection with the given ID.
   *
   * @param string $collection_id
   *   The collection ID.
   *
   * @return \Drupal\rdf_entity\RdfInterface
   *   The collection.
   */
  protected function loadCollection(string $collection_id): RdfInterface {
    return $this->entityTypeManager->getStorage('rdf_entity')->load($collection_id);
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
    return $this->getRequest()->get(MainContentViewSubscriber::WRAPPER_FORMAT, '');
  }

}
