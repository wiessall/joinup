<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\joinup_group\Plugin\Action\TransferGroupOwnershipAction;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Entity\OgRole;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Confirmation form for transferring the group ownership.
 */
class TransferGroupOwnershipConfirmForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * The user private tempstore.
   */
  protected PrivateTempStore $tempStore;

  /**
   * The membership of the user that receives the ownership.
   */
  protected OgMembershipInterface|null $membership = NULL;

  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly RendererInterface $renderer,
    #[Autowire(service: 'plugin.manager.action')]
    protected readonly ActionManager $actionPluginManager,
    #[Autowire(service: 'og.membership_manager')]
    protected readonly MembershipManagerInterface $membershipManager,
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
  ) {
    $this->tempStore = $tempStoreFactory->get('joinup_transfer_group_ownership');

    if ($data = $this->tempStore->get($this->currentUser->id())) {
      if (!$this->membership = OgMembership::load($data['membership'])) {
        throw new \RuntimeException("Invalid membership with ID '{$data['membership']}'");
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): MarkupInterface {
    return $this->t(
      'Are you sure you want to transfer the ownership of %group @label to %user?',
      $this->getDefaultMessageArguments(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): MarkupInterface|string {
    $memberships = $this->membershipManager->getGroupMembershipsByRoleNames($this->membership->getGroup(), [
      'administrator',
    ]);

    $current_owners = array_map(function (OgMembershipInterface $membership) {
      if ($membership->getOwnerId() === $this->currentUser->id()) {
        return $this->t('@user (you)', ['@user' => $membership->getOwner()->getDisplayName()]);
      }
      return $membership->getOwner()->getDisplayName();
    }, $memberships);

    $args = $this->getDefaultMessageArguments();

    $build = [];
    $build[] = [
      '#prefix' => '<h2>',
      '#markup' => $this->t('Warning!'),
      '#suffix' => '</h2>',
    ];
    if ($current_owners) {
      $build[] = [
        '#prefix' => '<p>',
        '#markup' => $this->formatPlural(count($current_owners),
          "The user %first_user will lose the ownership of the %group @label.",
          "The following users will lose the ownership of the %group @label:",
          $args + ['%first_user' => reset($current_owners)]
        ),
        '#suffix' => '</p>',
      ];
      if (count($current_owners) > 1) {
        $build[] = [
          '#theme' => 'item_list',
          '#items' => $current_owners,
        ];
      }
    }
    $build[] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('The user %user will be the new owner of %group @label.', $args),
      '#suffix' => '</p>',
    ];
    $build[] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('This action cannot be undone.'),
      '#suffix' => '</p>',
    ];

    return $this->renderer->render($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('devel.configs_list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Cleanup the bulk form selection.
    $this->tempStore->delete($this->currentUser->id());

    $group = $this->membership->getGroup();
    $memberships = $this->membershipManager->getGroupMembershipsByRoleNames($group, [
      'administrator',
    ]);
    // Revoke 'owner' role from existing group owners but grant them the
    // facilitator role, as a compensation, if missed.
    $facilitator_role = OgRole::loadByGroupAndName($group, 'facilitator');
    $administrator_role = OgRole::loadByGroupAndName($group, 'administrator');
    foreach ($memberships as $membership) {
      $membership
        ->revokeRole($administrator_role)
        ->addRole($facilitator_role)
        ->save();
    }

    // Add the 'owner' role, and also the 'facilitator' role, since in Joinup
    // every owner is also a facilitator.
    $this->membership
      ->addRole($administrator_role)
      ->addRole($facilitator_role)
      ->save();

    // Make this user also author of the group.
    $group->skip_notification = TRUE;
    $group->setOwner($this->membership->getOwner())->save();

    $former_owners = array_map(function (OgMembershipInterface $membership) {
      return $membership->getOwner()->getDisplayName();
    }, $memberships);

    $args = $this->getDefaultMessageArguments() + [
      '@users' => implode(', ', $former_owners),
    ];
    if ($former_owners) {
      $message = $this->formatPlural(count($former_owners),
        'Ownership of %group @label transferred from user @users to %user.',
        'Ownership of %group @label transferred from users @users to %user.',
        $args
      );
    }
    else {
      $message = $this->t('Ownership of %group @label granted to %user.', $args);
    }
    $this->messenger()->addStatus($message);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_transfer_group_ownership_confirm';
  }

  /**
   * Provide the access policy for the route that shows this form.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown when the group ownership transfer action plugin instance cannot be
   *   created.
   */
  public function access(): AccessResultInterface {
    // Probably we try to reuse a form that has been already used.
    if (!$this->membership) {
      return AccessResult::forbidden();
    }
    $action = $this->actionPluginManager->createInstance('joinup_transfer_group_ownership');
    assert($action instanceof TransferGroupOwnershipAction);
    return $action->access($this->membership, $this->currentUser, TRUE);
  }

  /**
   * Returns the default arguments for messaging.
   *
   * @return array
   *   The default arguments for messaging:
   *   - '%group': the label of the group the membership belongs to.
   *   - '@label': the group's singular label from its bundle entity.
   *   - '%user': the display name of the membership's owner.
   */
  protected function getDefaultMessageArguments(): array {
    $group = $this->membership->getGroup();
    $bundleKey = $group->getEntityType()->getKey('bundle');
    return [
      '%group' => $group->label(),
      '@label' => $group->get($bundleKey)->entity->getSingularLabel(),
      '%user' => $this->membership->getOwner()->getDisplayName(),
    ];
  }

}
