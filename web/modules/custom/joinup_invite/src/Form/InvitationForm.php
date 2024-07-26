<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Site\Settings;
use Drupal\joinup_invite\Entity\InvitationInterface;
use Drupal\joinup_invite\Event\InvitationEvent;
use Drupal\joinup_invite\Event\InvitationEventInterface;
use Drupal\joinup_invite\Event\InvitationEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Form that accepts a user's invitation to a group's entity.
 *
 * It is possible for certain users such as content owners, facilitators and
 * moderators to invite any user to look at certain content. The user will
 * receive an e-mail with an invitation link. The link leads to this page, which
 * will accept (or reject) the invitation. It also changes the status of the
 * invitation from 'pending' to 'accepted' (or 'rejected'). This data is stored
 * in the Message entity that was used to send the notification.
 *
 * For an example implementation, see the InviteToDiscussionForm.
 *
 * @see \Drupal\joinup_discussion\Form\InviteToDiscussionForm
 */
class InvitationForm extends FormBase {

  /**
   * The identifier for accepting an invitation.
   *
   * @var string
   */
  const string ACTION_ACCEPT = 'accept';

  /**
   * The identifier for rejecting an invitation.
   *
   * @var string
   */
  const string ACTION_REJECT = 'reject';

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected CurrentRouteMatch $currentRouteMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'invitation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?InvitationInterface $invitation = NULL): array {
    $form['text'] = [
      '#type' => 'markup',
      '#markup' =>
      $this->t(
          'You have been invited by @actor to join the "@entity" as a @role.',
          [
            '@actor' => $invitation->getOwner()->getDisplayName(),
            '@entity' => $invitation->getEntity()->label(),
            '@role' => $invitation->get('field_invitation_og_role')->entity->label(),
          ]
      ),
    ];

    $form['new_line1'] = [
      '#type' => 'markup',
      '#markup' => '<br /><br />',
    ];

    $form['question'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Do you accept invitation?'),
    ];

    $form['new_line2'] = [
      '#type' => 'markup',
      '#markup' => '<br /><br />',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Accept'),
      ],
      'reject' => [
        '#type' => 'submit',
        '#value' => $this->t('Reject'),
        '#submit' => ['::rejectForm'],
      ],
      'cancel' => [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#submit' => ['::redirectToGroup'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\joinup_invite\Entity\InvitationInterface $invitation */
    $invitation = $this->currentRouteMatch->getParameter('invitation');

    $invitation->accept()->save();
    $this->eventDispatcher->dispatch($this->getEvent($invitation, self::ACTION_ACCEPT), InvitationEvents::ACCEPT_INVITATION_EVENT);

    $this->redirectToGroup($form, $form_state);
  }

  /**
   * Rejects the form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function rejectForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\joinup_invite\Entity\InvitationInterface $invitation */
    $invitation = $this->currentRouteMatch->getParameter('invitation');
    $invitation->reject()->save();
    $this->eventDispatcher->dispatch($this->getEvent($invitation, self::ACTION_REJECT), InvitationEvents::REJECT_INVITATION_EVENT);

    $this->redirectToGroup($form, $form_state);
  }

  /**
   * Redirects to group.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function redirectToGroup(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\joinup_invite\Entity\InvitationInterface $invitation */
    $invitation = $this->currentRouteMatch->getParameter('invitation');
    $url = $invitation->getEntity()->toUrl();
    $form_state->setRedirect($url->getRouteName(), $url->getRouteParameters());
  }

  /**
   * Access check for the invitation route.
   *
   * Access is granted only if the hash value is correct. This allows users to
   * accept the invitation even if they are not currently logged in to the
   * website.
   *
   * @param \Drupal\joinup_invite\Entity\InvitationInterface $invitation
   *   The invitation which is being accepted or rejected.
   * @param string $hash
   *   The hash value to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(InvitationInterface $invitation, string $hash): AccessResultInterface {
    return AccessResult::allowedIf(static::generateHash($invitation) === $hash && $invitation->getStatus() === InvitationInterface::STATUS_PENDING);
  }

  /**
   * Returns a unique hash based on the invitation and action.
   *
   * This protects the invitations from being brute forced.
   *
   * @param \Drupal\joinup_invite\Entity\InvitationInterface $invitation
   *   The invitation.
   *
   * @return string
   *   A unique hash consisting of 8 lowercase alphanumeric characters, dashes
   *   and underscores.
   */
  public static function generateHash(InvitationInterface $invitation): string {
    $data = $invitation->id();
    // Keep backward compatibility as some emails could be already sent.
    $data .= 'accept';
    return strtolower(substr(Crypt::hmacBase64($data, Settings::getHashSalt()), 0, 8));
  }

  /**
   * Returns an InvitationEvent for the given invitation and action.
   *
   * @param \Drupal\joinup_invite\Entity\InvitationInterface $invitation
   *   The invitation for which to create an event.
   * @param string $action
   *   The action that has been taken on the invitation.
   *
   * @return \Drupal\joinup_invite\Event\InvitationEventInterface
   *   The event.
   */
  protected function getEvent(InvitationInterface $invitation, string $action): InvitationEventInterface {
    return (new InvitationEvent())
      ->setInvitation($invitation)
      ->setAction($action);
  }

}
