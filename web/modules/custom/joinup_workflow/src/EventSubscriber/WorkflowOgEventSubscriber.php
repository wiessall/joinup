<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\og\Event\GroupContentEntityOperationAccessEventInterface;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\GroupContentOperationPermission;
use Drupal\og\MembershipManagerInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters permission operation depending on the eLibrary creation.
 */
final class WorkflowOgEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a ContentCreationOgSubscriber object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected MembershipManagerInterface $ogMembershipManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Provides permissions for the 'Content Creation' group field.
      PermissionEventInterface::EVENT_NAME => 'contentCreationAlterPermissionEvent',
      // Performs access checks for OG group content operations.
      GroupContentEntityOperationAccessEventInterface::EVENT_NAME => [
        // Use a priority higher than the default subscriber from OG so that we
        // can have full control.
        ['checkEntityRevisionAccess', 10],
        ['ownerDeleteAccess', 5],
      ],
    ];
  }

  /**
   * Alters permission depending on the 'Content Creation' group field.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  // @todo Move this to a permission provider subscriber.
  public function contentCreationAlterPermissionEvent(PermissionEventInterface $event): void {
    $options = ['any_user', 'only_members', 'facilitators_and_authors'];
    foreach ($options as $option) {
      foreach (CommunityContentHelper::BUNDLES as $bundle) {
        $permission_string = 'create content for ' . $option . ' option';
        $permission = new GroupContentOperationPermission([
          'name' => $permission_string,
          'title' => $this->t('Create content for @option', [
            '@option' => $option,
          ]),
          'entityType' => 'node',
          'bundle' => $bundle,
          'operation' => $permission_string,
        ]);

        $event->setPermission($permission);
      }
    }
  }

  /**
   * Overrides delete access for user.
   *
   * According to the workflow, facilitators and authors can delete content
   * depending on the state, however, og will grant access depending only on
   * permission. Override and deny access to facilitators and authors depending
   * on the state.
   *
   * @param \Drupal\og\Event\GroupContentEntityOperationAccessEventInterface $event
   *   The OG group content entity operation access event.
   */
  public function ownerDeleteAccess(GroupContentEntityOperationAccessEventInterface $event): void {
    if ($event->getOperation() !== 'delete') {
      return;
    }

    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = $event->getGroup();
    $user = $event->getUser();
    $entity = $event->getGroupContent();
    $ownPermission = 'delete own ' . $entity->bundle() . ' content';
    $anyPermission = 'delete any ' . $entity->bundle() . ' content';

    // Unfortunately, because og allows access by default when the user has the
    // permission to delete their own content - so we deny access if the content
    // is not in the desired states, we have to first check if the user has the
    // global permission to delete the content so that we don't deny e.g. to
    // a moderator that is also the owner of the content.
    if ($user->hasPermission('delete any ' . $entity->bundle() . ' content')) {
      return;
    }

    // Only act on entities that have an owner. Cases, like the navigation menu
    // items, do not have an owner.
    if (!$entity instanceof EntityOwnerInterface) {
      return;
    }

    $hasOwnAccess = $entity->getOwnerId() === $user->id() && $group->hasGroupPermission((int) $user->id(), $ownPermission);
    $hasAnyAccess = $group->hasGroupPermission((int) $user->id(), $anyPermission);

    // We only need to check pre-moderated groups as for post-moderated groups
    // the user can delete the content depending only on the permission. If the
    // permission exists, access will be granted by og.
    if ($group->isModerated()) {
      // Deny access if the following conditions are met:
      // - The user is not the owner, has no "own" permission, but the entity is
      //   not in draft or archived state.
      // - The user has no "any" permission and the entity is not in draft
      //   state.
      $hasOwnAccess = $hasOwnAccess
        && in_array($entity->getWorkflowState(), ['draft', 'archived']);
      $hasAnyAccess = $hasAnyAccess && $entity->getWorkflowState() !== 'draft';
      if (!$hasOwnAccess && !$hasAnyAccess) {
        $event->denyAccess();
      }
    }
  }

  /**
   * Determines access to entity revisions for community content.
   *
   * @param \Drupal\og\Event\GroupContentEntityOperationAccessEventInterface $event
   *   The event fired when a group content entity operation is performed.
   */
  public function checkEntityRevisionAccess(GroupContentEntityOperationAccessEventInterface $event): void {
    $content = $event->getGroupContent();

    // Only act on community content.
    if (!$content instanceof CommunityContentInterface) {
      return;
    }

    $event->addCacheableDependency($content);

    // Do a quick exit if the event is not for the entity operations we handle.
    $operation = $event->getOperation();
    if (!in_array($operation, ['view revision', 'view all revisions', 'revert revision'])) {
      return;
    }

    $bundle = $content->bundle();
    $bundle_permission = "view $bundle revisions";

    // There should be at least two revisions. If the vid of the given node
    // and the vid of the default revision differ, then we already have two
    // different revisions so there is no need for a separate database check.
    // @todo This used to be the default behaviour in Drupal but this has since
    //   been changed. We might reconsider to show the revisions overview even
    //   if there is only 1 revision. This would be a function change that needs
    //   prior approval by the functional team.
    // @see https://www.drupal.org/node/3001224
    $node_storage = $this->entityTypeManager->getStorage('node');
    if ($content->isDefaultRevision() && ($node_storage->countDefaultLanguageRevisions($content) == 1)) {
      $event->denyAccess();
      return;
    }

    // The global "administer nodes" permissions gives full access to revisions.
    // @see parent::checkAccess()
    $account = $event->getUser();
    if ($account->hasPermission('administer nodes')) {
      $event->grantAccess();
      $event->stopPropagation();
      return;
    }

    // @todo Inject the service.
    $this->ogAccess = \Drupal::service('og.access');

    // Check if the user has group level permission to view all revisions, or
    // view revisions for the bundle of the group content.
    $group = $event->getGroup();
    $result = $this->ogAccess->userAccess($group, 'view all revisions', $account)
      ->orIf($this->ogAccess->userAccess($group, $bundle_permission, $account));

    // If the user owns the entity, check if they can 'view own revisions'.
    if (!$result->isAllowed() && (int) $content->getOwnerId() === (int) $account->id()) {
      $result = $result->orIf($this->ogAccess->userAccess($group, 'view own revisions', $account));
    }

    // If neither of the access checks are allowed, we have no opinion.
    if (!$result->isAllowed()) {
      return;
    }

    // Check if the access to the default revision and finally, if the
    // node passed in is not the default revision then access to that, too.
    $node_access = $this->entityTypeManager->getAccessControlHandler('node');
    $result = $result->andIf($node_access->access($node_storage->load($content->id()), 'view', $account, TRUE));
    if (!$content->isDefaultRevision()) {
      $result = $result->andIf($node_access->access($content, 'view', $account, TRUE));
    }

    if ($result->isAllowed()) {
      $event->grantAccess();
    }
  }

}
