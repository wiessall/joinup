<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\joinup_search\SearchIndexCommitter;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Base form class for share/unshare entity forms.
 */
abstract class ShareFormBase extends FormBase {

  use AutowireTrait;

  /**
   * The entity being shared.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'og.membership_manager')]
    protected readonly MembershipManagerInterface $membershipManager,
    #[Autowire(service: 'og.role_manager')]
    protected readonly OgRoleManagerInterface $roleManager,
    protected readonly AccountInterface $currentUser,
    protected readonly EntityTypeBundleInfoInterface $bundleInfo,
    protected readonly CacheTagsInvalidatorInterface $invalidator,
    protected readonly SearchIndexCommitter $searchIndexCommitter,
  ) {}

  /**
   * Gets a list of group ids where the current entity is already shared.
   *
   * @return array
   *   A list of group ids where the current entity is already shared on.
   */
  protected function getAlreadySharedGroupIds(): array {
    if (!$this->entity instanceof ShareableContentInterface) {
      return [];
    }

    return array_column($this->entity->getSharedInField()->getValue(), 'target_id');
  }

  /**
   * Retrieves a list of groups of a user filtered by specific a permission.
   *
   * @param string $permission
   *   A permission to filter the roles of the user by.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   An array of groups.
   */
  protected function getUserGroupsByPermission(string $permission): array {
    $roles = $this->roleManager->getRolesByPermissions([$permission]);
    if (empty($roles)) {
      return [];
    }

    $groups = $this->membershipManager->getUserGroupsByRoleIds($this->currentUser->id(), array_keys($roles), [OgMembershipInterface::STATE_ACTIVE], FALSE);
    return empty($groups) ? [] : $groups['node'];
  }

  /**
   * Returns the name of the permission needed for the given action.
   *
   * @param string $action
   *   The action name. Can be either 'share' or 'unshare'.
   *
   * @return string
   *   The permission name.
   */
  protected function getPermissionForAction(string $action): string {
    if (!in_array($action, ['share', 'unshare'])) {
      throw new \InvalidArgumentException('Only "share" and "unshare" are allowed as an action name.');
    }
    $type = $this->entity->getEntityTypeId() === 'node' ? 'content' : $this->entity->getEntityTypeId();
    return "{$action} {$this->entity->bundle()} {$type}";
  }

  /**
   * Returns a list of groups that the entity cannot be shared on.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The affiliated or parent group, if one exists.
   */
  protected function getExcludedParent(): ?GroupInterface {
    return $this->entity instanceof GroupContentInterface && $this->entity->hasGroup()
      ? $this->entity->getGroup()
      : NULL;
  }

  /**
   * Returns whether the form is displayed in a modal.
   *
   * @return bool
   *   TRUE if the form is displayed in a modal.
   *
   * @todo Remove when issue #2661046 is in.
   *
   * @see https://www.drupal.org/node/2661046
   */
  protected function isModal(): bool {
    return $this->getRequest()->query->get(MainContentViewSubscriber::WRAPPER_FORMAT) === 'drupal_modal';
  }

  /**
   * Returns whether the form is being handled with an ajax request.
   *
   * @return bool
   *   TRUE if the form is being handled through an AJAX request.
   */
  protected function isAjaxForm(): bool {
    return $this->getRequest()->query->has(FormBuilderInterface::AJAX_FORM_REQUEST);
  }

}
