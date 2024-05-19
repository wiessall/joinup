<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleManagerInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form class for share/unshare entity forms.
 */
abstract class ShareFormBase extends FormBase {

  const SHARED_IN_FIELD_NAMES = [
    'rdf_entity' => [
      'solution' => 'field_is_shared_in',
    ],
    'node' => [
      'discussion' => 'field_shared_in',
      'document' => 'field_shared_in',
      'event' => 'field_shared_in',
      'news' => 'field_shared_in',
    ],
  ];

  /**
   * The entity being shared.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  /**
   * Constructs a new ShareContentFormBase object.
   *
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorage $sparqlStorage
   *   The RDF entity storage.
   * @param \Drupal\Core\Entity\EntityViewBuilderInterface $rdfBuilder
   *   The RDF view builder.
   * @param \Drupal\og\MembershipManagerInterface $membershipManager
   *   The OG membership manager.
   * @param \Drupal\og\OgRoleManagerInterface $roleManager
   *   The OG role manager service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user account.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle info service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $invalidator
   *   The cache tag invalidator service.
   */
  public function __construct(
    protected SparqlEntityStorage $sparqlStorage,
    protected EntityViewBuilderInterface $rdfBuilder,
    protected MembershipManagerInterface $membershipManager,
    protected OgRoleManagerInterface $roleManager,
    protected AccountInterface $currentUser,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected CacheTagsInvalidatorInterface $invalidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('rdf_entity'),
      $container->get('entity_type.manager')->getViewBuilder('rdf_entity'),
      $container->get('og.membership_manager'),
      $container->get('og.role_manager'),
      $container->get('current_user'),
      $container->get('entity_type.bundle.info'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * Gets a list of group ids where the current entity is already shared.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   A list of group ids where the current entity is already shared on.
   */
  protected function getAlreadySharedGroupIds(): array {
    if (!$this->getSharedOnFieldName() || !$this->entity->hasField($this->getSharedOnFieldName())) {
      return [];
    }

    return array_column($this->entity->get($this->getSharedOnFieldName())->getValue(), 'target_id');
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
    return empty($groups) ? [] : $groups['rdf_entity'];
  }

  /**
   * Returns the name of the field that the entity uses for being shared.
   *
   * @return string|null
   *   The field name or null if not configured.
   */
  protected function getSharedOnFieldName(): ?string {
    return self::SHARED_IN_FIELD_NAMES[$this->entity->getEntityTypeId()][$this->entity->bundle()] ?? NULL;
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
   * For nodes, this is the parent group. For rdf entities, it is the affiliated
   * collection of the solution.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The affiliated or parent group, if one exists.
   */
  protected function getExcludedParent(): ?GroupInterface {
    if ($this->entity instanceof GroupContentInterface) {
      try {
        return $this->entity->getGroup();
      }
      catch (MissingGroupException $e) {
      }
    }
    return NULL;
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
