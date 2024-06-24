<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_core\Event\AjaxCommandEvent;
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

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The membership manager service.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected MembershipManagerInterface $membershipManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The time keeping service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The event dispatcher service.
   *
   * @var \Psr\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a SubscribeToCollectionForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The membership manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time keeping service.
   * @param \Psr\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MembershipManagerInterface $membership_manager, MessengerInterface $messenger, FormBuilderInterface $form_builder, TimeInterface $time, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipManager = $membership_manager;
    $this->messenger = $messenger;
    $this->formBuilder = $form_builder;
    $this->time = $time;
    $this->eventDispatcher = $event_dispatcher;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'subscribe_to_collection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RdfInterface $rdf_entity = NULL): array {
    // This form should only be accessible if the user is already a member of
    // the collection.
    $membership = $this->getUserNonBlockedMembership($rdf_entity);
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
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#extra_suggestion' => 'light_blue',
      '#weight' => 10,
      '#name' => 'cancel',
      '#op' => 'cancel',
      '#limit_validation_errors' => [],
    ];

    $form['actions']['confirm_subscribe'] = [
      '#type' => 'submit',
      '#op' => 'subscribe',
      '#value' => $this->t('Subscribe to notifications'),
      // This form is opened via AJAX in a modal dialog that is triggered by
      // submitting the JoinCollectionForm. By default, Drupal will send the
      // AJAX requests to the route of the original form, which in this case
      // would be the JoinCollectionForm. We need to manually specify the
      // callback URL and the query argument that triggers AJAX so that this
      // form will be loaded.
      // @see https://www.drupal.org/project/drupal/issues/2934463
      '#ajax' => [
        'callback' => '::confirmSubscription',
        'url' => Url::fromRoute('collection.subscribe_to_collection_form', [
          'rdf_entity' => $rdf_entity->id(),
        ]),
        'options' => [
          'query' => [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];
    $form['actions']['cancel']['#ajax'] = $form['actions']['confirm_subscribe']['#ajax'];
    $form['actions']['confirm_membership_only'] = [
      '#value' => $dontSubscribe = $this->t("Don't subscribe"),
      '#op' => 'no_subscribe',
    ] + $form['actions']['confirm_subscribe'];

    if ($rdf_entity instanceof CollectionInterface && $rdf_entity->hasCodeOfConduct()) {
      $this->mergeCodeOfConductComponents($form, $rdf_entity);
    }
    else {
      // User don't have to confirm the membership subscription since it is
      // created automatically.
      unset($form['actions']['confirm_membership_only']);
      // ...But, in this case, 'Cancel' means nothing (we don't need to take any
      // action), so the button will be just a "Close" button.
      $form['actions']['cancel']['#value'] = $dontSubscribe;
      // Check if the user is pending, so we can adapt the messages for the
      // user.
      $intro_message = $membership && $membership->isPending() ?
        $this->t('When your membership is approved you will be able to publish content in it.') :
        $this->t('You have joined the collection and you are now able to publish content in it.');

      $form['intro'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $intro_message,
        '#weight' => -1,
      ];
    }

    return $form;
  }

  /**
   * Adds the necessary code of conduct form elements to the form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\rdf_entity\RdfInterface|null $collection
   *   The collection.
   */
  protected function mergeCodeOfConductComponents(array &$form, ?RdfInterface $collection): void {
    $codeOfConduct = $collection->getSetting('code_of_conduct')->entity;
    if (!$collection->isClosed() || !$codeOfConduct instanceof ContentEntityInterface) {
      return;
    }

    $codeOfConductArgs = [
      ':code-of-conduct-url' => $codeOfConduct->toUrl()->toString(),
      '%code-of-conduct-label' => $codeOfConduct->label(),
    ];

    $extra['code_of_conduct_lead'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'To complete your application and send it for approval, we need to make sure you agree with our <a href=:code-of-conduct-url>%code-of-conduct-label</a>. This helps us maintain a safe and respectful environment for all our members.',
        $codeOfConductArgs,
      ),
    ];
    $extra['code_of_conduct_agreed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and agree to the <a href=:code-of-conduct-url>%code-of-conduct-label</a>.', $codeOfConductArgs),
      '#return_value' => $codeOfConduct->getRevisionId(),
      '#required' => TRUE,
      '#required_error' => $this->t('You must agree to the <a href=:code-of-conduct-url>%code-of-conduct-label</a> before asking for membership.', $codeOfConductArgs),
    ];

    $extra['code_of_conduct_trailer'] = [
      '#type' => 'item',
      '#markup' => $this->t('You will receive a confirmation email once your application has been approved. We look forward to having you as a valued member of our community!<br><br>If you have any questions or need assistance, please feel free to contact us.'),
    ];

    $form = [...$extra, ...$form];

    foreach (Element::children($form['actions']) as $buttonKey) {
      if (!str_starts_with($buttonKey, 'confirm')) {
        continue;
      }
      $form['actions'][$buttonKey]['#states'] = [
        'disabled' => [
          ':input[name="code_of_conduct_agreed"]' => ['checked' => FALSE],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $collection = $this->loadCollection($form_state->getValue('collection_id'));

    // The user should be a non-blocked member of the collection.
    $membership = $this->getUserNonBlockedMembership($collection);
    if (!$membership && !isset($form['code_of_conduct_agreed'])) {
      $form_state->setErrorByName('collection', $this->t('Please join the collection before subscribing to it.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $collection = $this->loadCollection($form_state->getValue('collection_id'));
    $operation = $form_state->getTriggeringElement()['#op'] ?? NULL;
    $parameters = ['%collection' => $collection->getName()];
    $codeOfConductHasBeenApproved = !empty($form_state->getValue('code_of_conduct_agreed'));

    $membership = $this->getUserNonBlockedMembership($collection);
    if (!$membership && $form_state->getValue('code_of_conduct_agreed')) {
      $state = $collection->isClosed() ? OgMembershipInterface::STATE_PENDING
        : OgMembershipInterface::STATE_ACTIVE;
      $membership = $this->createSaveNewMembership($state, $this->getUser(), $collection);
    }

    if ($operation !== 'subscribe' && $codeOfConductHasBeenApproved) {
      $message = isset($state) && $state === OgMembershipInterface::STATE_PENDING
        ? $this->t('You will get a notification once your membership at %collection is approved.', $parameters)
        : $this->t('You have been joined to %collection.', $parameters);
      $this->messenger->addStatus($message);
      return;
    }

    $subscription_bundles = [];
    foreach (JoinupSubscriptionsHelper::SUBSCRIPTION_BUNDLES['collection'] as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $subscription_bundles[] = [
          'entity_type' => $entity_type,
          'bundle' => $bundle,
        ];
      }
    }
    $membership->set('announcements', TRUE);
    $membership->set('subscription_bundles', $subscription_bundles)->save();

    // Check if the user is pending, so we can adapt the messages for the user.
    $is_pending = !empty($membership) && $membership->isPending();

    $message = $is_pending ?
      $this->t('You have been subscribed to %collection and will receive weekly notifications once your membership is approved.', $parameters) :
      $this->t('You have been subscribed to %collection and will receive weekly notifications. To manage your notifications go to <em>My subscriptions</em> in your user menu.', $parameters);
    $this->messenger->addStatus($message);
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
    $operation = $form_state->getTriggeringElement()['#op'] ?? '';

    if ($this->isAjax() && $operation === 'cancel') {
      $response->addCommand(new CloseModalDialogCommand());
    }

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
    elseif ($this->isAjax()) {
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
    $membership = $this->getUserNonBlockedMembership($rdf_entity);
    $codeOfConductField = $rdf_entity instanceof CollectionInterface
      ? $rdf_entity->getSetting('code_of_conduct')
      : NULL;
    return AccessResult::allowedIf(
      $user->isAuthenticated() &&
      (
        !empty($membership) ||
        $codeOfConductField && !$codeOfConductField->isEmpty()
      )
    );
  }

  /**
   * Access check for showing the subscribe form after authenticating a user.
   *
   * When an anonymous user wants to join a collection they first need to log in
   * and are then redirected back to the collection homepage so they can opt in
   * to the notification subscription. The desired collection is tracked in a
   * cookie. At the moment the user is logged in they will immediately become a
   * member of the collection but still need to opt in to notifications.
   *
   * This validates the following before showing the dialog:
   * - The cookie keeping track of the collection to join is present.
   * - The cookie tracks this collection.
   * - The user is an unblocked member of the collection.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection the user has joined.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\joinup_group\EventSubscriber\JoinGroupSubscriber::onLogin()
   */
  public function accessToSubscribeDialogAfterAuthenticating(RdfInterface $rdf_entity): AccessResultInterface {
    $cookie_id = $this->getRequest()->cookies->get('join_group', '');
    return AccessResult::allowedIf($cookie_id === $rdf_entity->id())
      ->andIf($this->access($rdf_entity));
  }

  /**
   * AJAX callback showing the subscribe form in a modal dialog.
   *
   * This is shown in response to a cookie being present which was set when the
   * user expressed their desire to join the collection before they logged in.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection the user has joined.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function showSubscribeDialog(RdfInterface $rdf_entity): AjaxResponse {
    $greetingField = $rdf_entity instanceof CollectionInterface
      ? $rdf_entity->getSetting('greeting')
      : NULL;
    $title = $greetingField && !$greetingField->isEmpty()
      ? Html::escape($greetingField->first()->getString())
      : $this->t('Welcome to %collection', ['%collection' => $rdf_entity->label()]);

    $modal_form = $this->formBuilder->getForm(SubscribeToCollectionForm::class, $rdf_entity);
    $modal_form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $response = new AjaxResponse();
    // Allow modules to alter or replace the Ajax command.
    $event = new AjaxCommandEvent(new OpenModalDialogCommand($title, $modal_form, [
      'width' => '500',
    ]), [
      'instance' => 'join_collection.subscribe_dialog.open',
      'title' => $title,
      'content' => $modal_form,
    ]);
    $this->eventDispatcher->dispatch($event);
    $response->addCommand($event->getCommand())->addAttachments($event->getAttachments());

    // This modal should only be shown once, immediately after the user logs in.
    // Deleting the cookie ensures it will not be shown again.
    setcookie('join_group', '', $this->time->getRequestTime() - 86400, '/');

    // Ensure the response is not cached, this code needs to run so the cookie
    // can be deleted.
    $response->setMaxAge(0);

    return $response;
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
