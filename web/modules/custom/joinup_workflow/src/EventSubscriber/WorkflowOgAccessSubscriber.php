<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\og\Event\GroupContentEntityOperationAccessEventInterface;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\GroupContentOperationPermission;
use Drupal\og\MembershipManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters permission operation depending on the eLibrary creation.
 */
final class WorkflowOgAccessSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a ContentCreationOgSubscriber object.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected MembershipManagerInterface $ogMembershipManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PermissionEventInterface::EVENT_NAME => 'onPermissionEvent',
      GroupContentEntityOperationAccessEventInterface::EVENT_NAME => [
        ['ownerDeleteAccess', 10],
      ],
    ];
  }

  /**
   * Provides permissions for operation depending on the eLibrary creation.
   */
  public function onPermissionEvent(PermissionEventInterface $event): void {
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

}
