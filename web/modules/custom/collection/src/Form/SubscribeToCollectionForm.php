<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
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
    $greetingField = NULL;
    try {
      $greetingField = $rdf_entity->getSetting('greeting');
    }
    catch (\Exception) {
    }
    return $greetingField && !$greetingField->isEmpty()
      ? Html::escape($greetingField->first()->getString())
      : t('Welcome to the community!');
  }

  /**
   * Returns the referer URL if form was accessed from an internal route.
   *
   * @return \Drupal\Core\Url|null
   *   The referer URL if form was accessed from an internal route, NULL
   *   otherwise.
   */
  protected function getRefererUrl(): ?Url {
    static $originalUrl;
    if (!isset($originalUrl)) {
      $referer = $this->getRequest()->headers->get('referer');
      if ($referer && UrlHelper::externalIsLocal($referer, $this->getRequest()->getSchemeAndHttpHost())) {
        $originalUri = parse_url($referer, PHP_URL_PATH);
        if ($query = parse_url($referer, PHP_URL_QUERY)) {
          $originalUri .= "?$query";
        }
        $originalUrl = $this->pathValidator->getUrlIfValid($originalUri);
      }
      else {
        $originalUrl = FALSE;
      }
    }

    return $originalUrl instanceof Url ? $originalUrl : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RdfInterface $rdf_entity = NULL): array {
    $coc_components = $this->getCodeOfConductComponents($rdf_entity);
    $has_coc = !empty($coc_components);
    $membership = $this->getUserNonBlockedMembership($rdf_entity);
    $intro_message = $membership && $membership->isPending() ?
      $this->t('When your membership is approved you will be able to publish content in it.') :
      $this->t('You have joined the collection and you are now able to publish content in it.');
    $form = $coc_components ?? [
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $intro_message,
        '#weight' => -1,
      ],
    ];

    $referer = $form_state->getUserInput()['referer'] ?? $this->getRefererUrl()->toString();
    $form['referer'] = [
      '#type' => 'hidden',
      '#default_value' => $referer,
    ];
    // Submit buttons (excluding "Cancel") must be disabled unless the checkbox
    // of CoC is checked.
    $submit_states = $has_coc
      ? [
        'disabled' => [
          ':input[name="code_of_conduct_agreed"]' => ['checked' => FALSE],
        ],
      ]
      : [];

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

    $submit_button_base = [
      '#type' => 'submit',
      // This form can be opened via AJAX in a modal dialog that is triggered by
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

    $form['actions']['confirm_subscribe'] = [
      '#value' => $this->t('Subscribe to notifications'),
      '#states' => $submit_states,
      '#op' => 'subscribe',
    ] + $submit_button_base;
    if ($this->isAjax()) {
      $form['actions']['cancel'] = [
        '#type' => 'button',
        '#extra_suggestion' => 'light_blue',
        '#weight' => 10,
        '#value' => $this->t("Don't subscribe"),
        '#op' => 'cancel',
        '#limit_validation_errors' => [],
        '#ajax' => $submit_button_base['#ajax'],
      ];
    }
    else {
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#attributes' => ['class' => ['button']],
        '#url' => Url::fromUserInput($referer),
        '#weight' => 10,
        '#title' => $this->t("Don't subscribe"),
      ];
    }

    if (!$has_coc) {
      return $form;
    }

    $form['actions']['cancel']['#value'] = $this->t('Cancel');
    $form['actions']['cancel']['#title'] = $this->t('Cancel');
    $form['actions']['confirm_membership_only'] = [
      '#value' => $this->t("Don't subscribe"),
      '#states' => $submit_states,
      '#op' => 'no_subscribe',
    ] + $submit_button_base;

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
      '%code-of-conduct-label' => $codeOfConduct->label(),
    ];

    $codeOfConductComponents['code_of_conduct_lead'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'To complete your application and send it for approval, we need to make sure you agree with our <a href=:code-of-conduct-url target="_blank">%code-of-conduct-label</a>. This helps us maintain a safe and respectful environment for all our members.',
        $codeOfConductArgs,
      ),
    ];
    $codeOfConductComponents['code_of_conduct_agreed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and agree to the <a href=:code-of-conduct-url target="_blank">%code-of-conduct-label</a>.', $codeOfConductArgs),
      '#return_value' => $codeOfConduct->getRevisionId(),
      '#required' => TRUE,
      '#required_error' => $this->t('You must agree to the <a href=:code-of-conduct-url>%code-of-conduct-label</a> before asking for membership.', $codeOfConductArgs),
    ];

    $codeOfConductComponents['code_of_conduct_trailer'] = [
      '#type' => 'item',
      '#markup' => $this->t('You will receive a confirmation email once your application has been approved. We look forward to having you as a valued member of our community!<br><br>If you have any questions or need assistance, please feel free to contact us.'),
    ];

    return $codeOfConductComponents;
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

    if (!$this->isAjax()) {
      $referer = $form_state->getValue('referer');
      $form_state->setRedirectUrl(
        $referer
          ? Url::fromUserInput($referer)
          : $collection->toUrl()
      );
    }

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
      $this->messenger()->addStatus($message);
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
    $this->messenger()->addStatus($message);
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
    $hasCodeOfConductToAccept = $rdf_entity instanceof CollectionInterface && $rdf_entity->hasCodeOfConductToAccept();
    return AccessResult::allowedIf(
      $user->isAuthenticated() &&
      (!empty($membership) ||  $hasCodeOfConductToAccept)
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
  public function showSubscribeDialog(GroupInterface $rdf_entity): AjaxResponse {
    $modal_form = $this->formBuilder->getForm(SubscribeToCollectionForm::class, $rdf_entity);
    $modal_form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $response = new AjaxResponse();
    // Allow modules to alter or replace the Ajax command.
    $event = new AjaxCommandEvent(new OpenModalDialogCommand(static::title($rdf_entity), $modal_form, [
      'width' => '500',
    ]), [
      'instance' => 'join_collection.subscribe_dialog.open',
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
