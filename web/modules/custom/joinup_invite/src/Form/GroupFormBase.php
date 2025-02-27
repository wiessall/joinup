<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_invite\InvitationMessageHelperInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgRoleInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Form to add a member with a certain role in a group.
 */
abstract class GroupFormBase extends InviteFormBase {

  use AutowireTrait;

  /**
   * The OG role to assign if the invitation is accepted.
   */
  protected OgRoleInterface $role;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'joinup_invite.invitation_message_helper')]
    InvitationMessageHelperInterface $invitationMessageHelper,
    #[Autowire(service: 'logger.channel.group_membership')]
    LoggerChannelInterface $logger,
    #[Autowire(service: 'og.membership_manager')]
    protected MembershipManagerInterface $ogMembershipManager,
  ) {
    parent::__construct($entityTypeManager, $invitationMessageHelper, $logger);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCancelButtonUrl(): Url {
    return new Url("entity.{$this->entity->getEntityTypeId()}.member_overview", [
      $this->entity->getEntityTypeId() => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $node = NULL): array {
    $this->entity = $node;
    $form = parent::build($form, $form_state);

    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#required' => TRUE,
      '#options' => [
        'member' => $this->t('Member'),
        'author' => $this->t('Author'),
        'facilitator' => $this->t('Facilitator'),
      ],
      '#default_value' => 'member',
    ];

    return $form;
  }

}
