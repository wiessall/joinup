<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgAccessInterface;
use Drupal\og\Plugin\Action\DeleteOgMembership;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes a group membership.
 */
#[Action(
  id: "joinup_og_membership_delete_action",
  label: new TranslatableMarkup("Delete the selected membership(s) from solution/collection"),
  type: "og_membership",
  confirm_form_route_name: "joinup_group.membership_delete_action.confirm"
)]
class DeleteGroupMembership extends DeleteOgMembership {

  /**
   * The user private tempstore.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $privateTempStore;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    OgAccessInterface $og_access,
    PrivateTempStoreFactory $temp_store_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $og_access);
    $this->privateTempStore = $temp_store_factory->get('joinup_group.og_membership_delete_action');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('og.access'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $memberships): void {
    $this->privateTempStore->set('memberships', $memberships);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?OgMembership $membership = NULL): void {
    $this->executeMultiple([$membership]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($membership, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\og\Entity\OgMembership $membership */
    // 'Joinup' collection membership cannot be revoked.
    $group = $membership->getGroup();
    assert($group instanceof GroupInterface);
    if ($group->getUri() === JoinupCollectionHelper::JOINUP_COLLECTION_URI) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }
    return parent::access($membership, $account, $return_as_object);
  }

}
