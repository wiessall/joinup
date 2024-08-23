<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_invite\InvitationMessageHelperInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgRoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to add a member with a certain role in a group.
 */
abstract class GroupFormBase extends InviteFormBase {

  /**
   * The OG role to assign if the invitation is accepted.
   */
  protected OgRoleInterface $role;

  /**
   * Constructs a new InviteToGroupForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\joinup_invite\InvitationMessageHelperInterface $invitationMessageHelper
   *   The invitation message helper service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   * @param \Drupal\og\MembershipManagerInterface $ogMembershipManager
   *   The og membership manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    InvitationMessageHelperInterface $invitationMessageHelper,
    LoggerChannelInterface $logger,
    protected MembershipManagerInterface $ogMembershipManager,
  ) {
    parent::__construct($entityTypeManager, $invitationMessageHelper, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('joinup_invite.invitation_message_helper'),
      $container->get('logger.channel.group_membership'),
      $container->get('og.membership_manager'),
    );
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
