<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Drupal\joinup_group\Form\JoinGroupFormBase;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\sparql_entity_storage\UriEncoder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A simple form with a button to join or leave a collection.
 */
class JoinCollectionForm extends JoinGroupFormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state, ?AccountProxyInterface $user = NULL, ?RdfInterface $group = NULL): array {
    $form = parent::buildForm($form, $form_state, $user, $group);

    // @todo Move this into the joinup_subscription module to solve a circular
    //   dependency.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6330
    $membership = $this->getUserNonBlockedMembership();
    if (empty($membership)) {
      // Show the subscription dialog in a modal on join.
      $form['join']['#ajax'] = ['callback' => '::showSubscribeDialog'];
    }
    else {
      // Show the subscription dialog in a modal on page load, if the user has
      // just authenticated after indicating their desire to join.
      $form['#attached']['library'][] = 'collection/subscribe_dialog';
      $form['#attached']['drupalSettings']['joinGroupData'] = [
        'sparqlEncodedId' => UriEncoder::encodeUrl($group->id()),
        'id' => $group->id(),
      ];
    }

    // Rebuild button for refreshing the form if membership is created by the
    // SubscribeToCollectionForm.
    $form['rebuild'] = [
      '#type' => 'button',
      '#value' => $this->t('Rebuild'),
      '#attributes' => ['class' => ['visually-hidden']],
      '#name' => 'join_collection_rebuild',
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
    \assert($this->group instanceof CollectionInterface);
    $form_state->setRedirectUrl(
      $this->group->toUrl('subscribe')
        ->setOption('query', [
          'destination' => Url::fromRoute('<current>')->toString(),
        ])
    );

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
      $modal_form = $this->formBuilder->getForm(SubscribeToCollectionForm::class, $this->group);
      $modal_form['#attached']['library'][] = 'core/drupal.dialog.ajax';

      // Allow modules to alter or replace the Ajax command.
      $event = new AjaxCommandEvent(new OpenModalDialogCommand(SubscribeToCollectionForm::title($this->group), $modal_form, [
        'width' => '500',
      ]), [
        'instance' => 'join_collection.subscribe_dialog.open',
        'content' => $modal_form,
      ]);
      $this->eventDispatcher->dispatch($event);
      $response->addCommand($event->getCommand())->addAttachments($event->getAttachments());
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getJoinSubmitLabel(): TranslatableMarkup {
    if (!$this->group->getSetting('label_join')->isEmpty()) {
      return new TranslatableMarkup(
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $this->group->getSetting('label_join')->getString(),
        [], ['context' => 'collection'],
      );
    }

    return parent::getJoinSubmitLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getLeaveSubmitLabel(): TranslatableMarkup {
    if (!$this->group->getSetting('label_leave')->isEmpty()) {
      return new TranslatableMarkup(
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $this->group->getSetting('label_leave')->getString(),
        [], ['context' => 'collection'],
      );
    }

    return parent::getLeaveSubmitLabel();
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
   * Returns the HTML ID of the form wrapper.
   *
   * @return string
   *   The HTML ID of the form wrapper.
   */
  protected function ajaxWrapperId(): string {
    return $this->getFormId() . '_ajax_wrapper';
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
