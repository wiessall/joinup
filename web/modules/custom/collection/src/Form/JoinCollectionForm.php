<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

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
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Form\JoinGroupFormBase;
use Drupal\og\MembershipManagerInterface;
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
  public function buildForm(array $form, FormStateInterface $form_state, ?AccountProxyInterface $user = NULL, ?GroupInterface $group = NULL): array {
    $form = parent::buildForm($form, $form_state, $user, $group);

    // @todo Move this into the joinup_subscription module to solve a circular
    //   dependency.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6330
    $membership = $this->getUserNonBlockedMembership($user, $group);
    if (empty($membership)) {
      // Show the subscription dialog in a modal on join.
      $form['join']['#ajax'] = ['callback' => '::showSubscribeDialog'];
    }
    else {
      // Show the subscription dialog in a modal on page load, if the user has
      // just authenticated after indicating their desire to join.
      $form['#attached']['library'][] = 'collection/subscribe_dialog';
      $form['#attached']['drupalSettings']['joinGroupData'] = [
        'id' => $group->id(),
      ];
    }

    return $form;
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
      $form_button = $this->formBuilder->rebuildForm('join_group_form', $form_state, $form);
      $response->addCommand(new ReplaceCommand('#join-group-form', $form_button));

      $title = $this->t('Welcome to %collection', ['%collection' => $this->group->label()]);

      $modal_form = $this->formBuilder->getForm(SubscribeToCollectionForm::class, $this->group);
      $modal_form['#attached']['library'][] = 'core/drupal.dialog.ajax';

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

}
