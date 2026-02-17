<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup_invite\Entity\Invitation;
use Drupal\joinup_invite\InvitationMessageHelperInterface;

/**
 * Behat step definitions for testing invitations.
 */
class JoinupInviteContext extends RawDrupalContext {

  use EntityTrait;
  use UserTrait;

  /**
   * Visits the invitation form.
   *
   * @param string $action
   *   The action being taken on the invitation, can be either 'accept' or
   *   'reject'.
   * @param string $title
   *   The title of the entity the user has been invited to.
   * @param string $bundle
   *   The bundle of the entity the user has been invited to.
   * @param string $type
   *   The type of the entity the user has been invited to. Either 'group' or
   *   'content'.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the action is anything other than 'accept' or 'reject'.
   */
  #[When('I visit the :action invitation form for the :title :bundle :type')]
  public function visitInvitationForm(string $action, string $title, string $bundle, string $type): void {
    if (!in_array($type, ['group', 'content'])) {
      throw new \InvalidArgumentException("Unknown type '$action'. Valid actions are 'group' and 'content'.");
    }
    if (!in_array($action, ['accept', 'reject'])) {
      throw new \InvalidArgumentException("Unknown action '$action'. Valid actions are 'accept' and 'reject'.");
    }

    $entity = $this->getEntityByLabel('node', $title, $bundle);
    $user = $this->userManager->getCurrentUser();
    $user = $this->getUserByName($user->name);

    $invitation_info = $this->getInvitationInfo($bundle);
    $invitation = Invitation::loadByEntityAndUser($entity, $user, $invitation_info['invitation_bundle']);
    $arguments = $this->getInvitationMessageHelper()->getMessage($invitation, $invitation_info['message_template'])->getArguments();

    $this->visitPath($arguments["@invitation:{$action}_url"]);
  }

  /**
   * Returns the service that assists in working with messages for invitations.
   *
   * @return \Drupal\joinup_invite\InvitationMessageHelperInterface
   *   The service.
   */
  protected function getInvitationMessageHelper(): InvitationMessageHelperInterface {
    return \Drupal::service('joinup_invite.invitation_message_helper');
  }

}
