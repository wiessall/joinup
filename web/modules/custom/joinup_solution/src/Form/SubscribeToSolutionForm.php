<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
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
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Traits\MembershipCreateTrait;
use Drupal\joinup_solution\Entity\Solution;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form suggesting the user to subscribe to a solution after joining.
 *
 * This form is shown in a modal dialog after the user joins a solution.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6330
 * @see \Drupal\collection\Form\JoinCollectionForm::showSubscribeDialog()
 * @todo Move this into the joinup_subscription module to solve a circular
 *   dependency.
 */
class SubscribeToSolutionForm extends FormBase {

  use MembershipCreateTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MembershipManagerInterface $membershipManager,
    protected $messenger,
    protected readonly FormBuilderInterface $formBuilder,
    protected readonly TimeInterface $time,
    protected readonly EventDispatcherInterface $eventDispatcher,
    protected readonly PathValidatorInterface $pathValidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
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
    return 'subscribe_to_solution_form';
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
    $form = [
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t("You're now an active member of this space and ready to participate fully!"),
        '#weight' => -3,
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('You can upgrade your notification preferences from your profile page at any time.'),
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

    // Keep track of the collection we are subscribing to.
    $form['solution_id'] = [
      '#type' => 'value',
      '#value' => $node->id(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // This form can be opened via AJAX in a modal dialog that is triggered by
    // submitting the JoinSolutionForm. By default, Drupal will send the
    // AJAX requests to the route of the original form, which in this case
    // would be the JoinSolutionForm. We need to manually specify the
    // callback URL and the query argument that triggers AJAX so that this
    // form will be loaded.
    // @see https://www.drupal.org/project/drupal/issues/2934463
    $modal_form_url = Url::fromRoute('joinup_solution.subscribe', [
      'node' => $node->id(),
    ], ['absolute' => TRUE]);

    $ajax_callback = [
      'callback' => '::confirmSubscription',
      'url' => $modal_form_url,
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $operation = $form_state->getTriggeringElement()['#op'] ?? NULL;
    if ($operation === 'cancel') {
      $form_state->clearErrors();
      return;
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $operation = $form_state->getTriggeringElement()['#op'] ?? NULL;
    if ($operation === 'cancel') {
      return;
    }
    $subscribe = (bool) $form_state->getValue('subscribe');
    if ($subscribe) {
      $solutionId = (int) $form_state->getValue('solution_id');
      $solution = Solution::load($solutionId);
      $membership = $this->createSolutionMembership(OgMembershipInterface::STATE_ACTIVE, [],$this->getUser(), $solution);
      $membership->save();
      $this->messenger->addStatus($solution->getNewMembershipSuccessMessage($membership));
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
        'instance' => 'join_solution.subscribe_dialog.close',
      ]);
      $this->eventDispatcher->dispatch($event);

      $response->addCommand($event->getCommand());
      $response->addCommand(new AfterCommand('*[data-drupal-messages-fallback]', $messages));
      $response->addCommand(new InvokeCommand('[name="join_solution_rebuild"]', 'trigger', ['mousedown']));
    }
    else {
      $response->addCommand(new RemoveCommand('#drupal-modal form > [data-drupal-messages], #drupal-modal form > [data-drupal-messages-fallback]'));
      $response->addCommand(new PrependCommand('#drupal-modal form', $messages));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function createSolutionMembership(string $state, array $roles, UserInterface $user, GroupInterface $group): OgMembershipInterface {
    $membership = $this->createMembership($state, $roles, $user, $group);

    $bundles = [];
    foreach (CommunityContentHelper::BUNDLES as $bundle) {
      $bundles[] = ['entity_type' => 'node', 'bundle' => $bundle];
    }

    $membership->set('subscription_bundles', $bundles);

    return $membership;
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
    return $this->getRequest()
      ->get(MainContentViewSubscriber::WRAPPER_FORMAT, '');
  }

}
