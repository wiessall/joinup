<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action allowing an admin to transfer a group ownership.
 */
#[Action(
  id: "joinup_transfer_group_ownership",
  label: new TranslatableMarkup("Transfer group ownership"),
  type: "og_membership",
  confirm_form_route_name: "joinup_group.transfer_group_ownership_confirm"
)]
class TransferGroupOwnershipAction extends ActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The Joinup group transfer temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * Messages collector in case of warnings or errors.
   *
   * @var array[]
   */
  protected array $messages = [];

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    PrivateTempStoreFactory $temp_store_factory,
    protected readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempStore = $temp_store_factory->get('joinup_transfer_group_ownership');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tempstore.private'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $memberships): void {
    // We only allow one user to pickup the membership.
    if (count($memberships) > 1) {
      $membership = reset($memberships);
      $this->messages['warning'][] = $this->t(
        'You cannot transfer the @label ownership to more than one user. Please select a single user.',
        static::getDefaultMessageArguments($membership),
      );
    }
    parent::executeMultiple($memberships);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?OgMembership $membership = NULL): void {
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = $membership->getGroup();
    $user = $membership->getOwner();

    // Is the selected member already the owner?
    if ($group->isGroupOwner((int) $user->id())) {
      $this->messages['warning'][] = $this->t(
        'Member %member is already the owner of %group @label. Please select other user.',
        static::getDefaultMessageArguments($membership),
      );
    }

    // After validating, store the data in the user private tempstore. We'll
    // retrieve the information later, in the confirm form submit callback or in
    // the redirect alter event subscriber.
    // @see \Drupal\joinup_group\Form\TransferGroupOwnershipConfirmForm
    // @see \Drupal\joinup_group\EventSubscriber\TransferGroupOwnershipSubscriber
    $this->tempStore->set($this->currentUser->id(), [
      'membership' => $membership->id(),
      'messages' => $this->messages,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($membership, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\og\OgMembershipInterface $membership */
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = $membership->getGroup();
    $can_transfer_ownership = $this->canTransferOwnership($group, $account);
    return $return_as_object ? ($can_transfer_ownership ? AccessResult::allowed() : AccessResult::forbidden()) : $can_transfer_ownership;
  }

  /**
   * Checks if a user is able to transfer the ownership of a given group.
   *
   * The ownership transfer is allowed when the transfer is triggered either by
   * a user that has site-wide permissions for such operations or the user is
   * the current owner of the group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group whom ownership is about to be transferred.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to be checked.
   *
   * @return bool
   *   If the user is allowed to transfer the ownership of the group.
   */
  public function canTransferOwnership(GroupInterface $group, AccountInterface $account): bool {
    // The user has proper site-wide permission for this operation.
    return $account->hasPermission("administer {$group->bundle()} ownership") ||
      // Or the user is the current group owner.
      $group->isGroupOwner((int) $account->id());
  }

  /**
   * Returns the default arguments for messaging.
   *
   * @param \Drupal\og\OgMembershipInterface $membership
   *   The membership object.
   *
   * @return array
   *   The default arguments for messaging:
   *   - '%group': the label of the group the membership belongs to.
   *   - '@label': the group's singular label from its bundle entity.
   *   - '%member': the display name of the membership's owner.
   */
  protected static function getDefaultMessageArguments(OgMembershipInterface $membership): array {
    $group = $membership->getGroup();
    $bundleKey = $group->getEntityType()->getKey('bundle');
    return [
      '%member' => $membership->getOwner()->getDisplayName(),
      '%group' => $group->label(),
      '@label' => $group->get($bundleKey)->entity->getSingularLabel(),
    ];
  }

}
