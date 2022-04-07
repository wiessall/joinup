<?php

declare(strict_types = 1);

namespace Drupal\joinup_community_content\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_workflow\Event\StateMachineButtonLabelsEventInterface;
use Drupal\joinup_workflow\Event\UnchangedWorkflowStateUpdateEvent;
use Drupal\og\Event\GroupContentEntityOperationAccessEventInterface;
use Drupal\og\Event\PermissionEventInterface as OgPermissionEventInterface;
use Drupal\og\GroupContentOperationPermission;
use Drupal\og\GroupPermission;
use Drupal\og\OgAccessInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscribers for the Joinup community content module.
 */
class EventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The service providing information about bundles.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The currently logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The OG access handler.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected OgAccessInterface $ogAccess;

  /**
   * Constructs an EventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The service providing information about bundles.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged in user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access handler.
   */
  public function __construct(
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    OgAccessInterface $og_access
  ) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->ogAccess = $og_access;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Use a priority higher than the default subscriber from OG so that we
      // can have full control.
      GroupContentEntityOperationAccessEventInterface::EVENT_NAME => [
        'checkEntityRevisionAccess', 10,
      ],
      OgPermissionEventInterface::EVENT_NAME => [['provideOgRevisionPermissions']],
      StateMachineButtonLabelsEventInterface::EVENT_NAME => 'provideStateMachineButtonLabels',
      'joinup_workflow.unchanged_workflow_state_update' => 'onUnchangedWorkflowStateUpdate',
    ];
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
    if (!in_array($operation, ['view all revisions', 'revert revision'])) {
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

  /**
   * Declare OG permissions for handling revisions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideOgRevisionPermissions(OgPermissionEventInterface $event) {
    $group_content_bundle_ids = $event->getGroupContentBundleIds();

    if (!empty($group_content_bundle_ids['node'])) {
      // Add a global permission that allows to access all the revisions.
      $event->setPermissions([
        new GroupPermission([
          'name' => 'view all revisions',
          'title' => $this->t('View all revisions'),
          'restrict access' => TRUE,
        ]),
        new GroupPermission([
          'name' => 'view own revisions',
          'title' => $this->t('View own revisions'),
          'restrict access' => TRUE,
        ]),
        new GroupPermission([
          'name' => 'revert all revisions',
          'title' => $this->t('Revert all revisions'),
          'restrict access' => TRUE,
        ]),
        new GroupPermission([
          'name' => 'delete all revisions',
          'title' => $this->t('Delete all revisions'),
          'restrict access' => TRUE,
        ]),
        new GroupPermission([
          'name' => 'administer shared entities',
          'title' => $this->t('Administer shared entities'),
          'restrict access' => TRUE,
        ]),
      ]);

      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo('node');
      foreach ($group_content_bundle_ids['node'] as $bundle_id) {
        $bundle_label = $bundle_info[$bundle_id]['label'];

        $event->setPermissions([
          new GroupContentOperationPermission([
            'name' => "view $bundle_id revisions",
            'title' => $this->t('%bundle: View revisions', ['%bundle' => $bundle_label]),
            'operation' => 'view revision',
            'entity type' => 'node',
            'bundle' => $bundle_id,
          ]),
          new GroupContentOperationPermission([
            'name' => "revert $bundle_id revisions",
            'title' => $this->t('%bundle: Revert revisions', ['%bundle' => $bundle_label]),
            'operation' => 'revert revision',
            'entity type' => 'node',
            'bundle' => $bundle_id,
          ]),
          new GroupContentOperationPermission([
            'name' => "delete $bundle_id revisions",
            'title' => $this->t('%bundle: Delete revisions', ['%bundle' => $bundle_label]),
            'operation' => 'delete revision',
            'entity type' => 'node',
            'bundle' => $bundle_id,
          ]),
          new GroupContentOperationPermission([
            'name' => "share $bundle_id content",
            'title' => $this->t('%bundle: Share onto a group', ['%bundle' => $bundle_label]),
            'operation' => 'share',
            'entity type' => 'node',
            'bundle' => $bundle_id,
          ]),
          new GroupContentOperationPermission([
            'name' => "unshare $bundle_id content",
            'title' => $this->t('%bundle: Unshare from a group', ['%bundle' => $bundle_label]),
            'operation' => 'unshare',
            'entity type' => 'node',
            'bundle' => $bundle_id,
          ]),
        ]);
      }
    }
  }

  /**
   * Updates transition button labels depending on who performs the transition.
   *
   * This will check if a transition from validated to proposed is being
   * performed for community content and depending on the user who initiates
   * this transition the label will be:
   * - 'Request changes': if a facilitator or moderator changes validated
   *   content to proposed state, in order for the original author to make some
   *   requested changes.
   * - 'Propose changes': if the author of community content in a pre-moderated
   *   collection wants to change some of their community content and needs this
   *   change to be approved and published by a facilitator.
   *
   * @param \Drupal\joinup_workflow\Event\StateMachineButtonLabelsEventInterface $event
   *   The event being fired.
   */
  public function provideStateMachineButtonLabels(StateMachineButtonLabelsEventInterface $event): void {
    // Exit through the escape hatch if we do not have the transition that we
    // want to alter.
    if (!array_key_exists('propose_new_revision', $event->getTransitions())) {
      return;
    }

    // If the content is not currently in validated state, then this is not the
    // content we are looking for.
    $state_id = $event->getStateId();
    if ($state_id !== 'validated') {
      return;
    }

    // Only act if we are dealing with community content in a moderated group.
    $entity = $event->getEntity();
    if (!$entity instanceof CommunityContentInterface) {
      return;
    }
    $group = $entity->getGroup();
    if (!$group->isModerated()) {
      return;
    }

    // Determine if we are a privileged user such as a facilitator or moderator.
    // Only privileged users can update content in validated state directly, all
    // other users first need to pass through proposed state.
    $is_privileged = $entity->isTargetWorkflowStateAllowed($state_id, $state_id);

    $label = $is_privileged ? $this->t('Request changes') : $this->t('Propose changes');
    $event->updateLabel('propose_new_revision', (string) $label);
  }

  /**
   * Determines if the content be updated without changing workflow state.
   *
   * @param \Drupal\joinup_workflow\Event\UnchangedWorkflowStateUpdateEvent $event
   *   The event.
   */
  public function onUnchangedWorkflowStateUpdate(UnchangedWorkflowStateUpdateEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof CommunityContentInterface) {
      return;
    }

    $state = $event->getState();
    $permitted = $entity->isTargetWorkflowStateAllowed($state, $state);
    $access = AccessResult::forbiddenIf(!$permitted);
    $access->addCacheContexts(['user.roles', 'og_role']);
    $event->setAccess($access);

    // Set a custom button label as defined in the functional specification.
    switch ($state) {
      case 'draft':
        $event->setLabel($this->t('Save as draft'));
        break;

      case 'proposed':
      case 'validated':
        $event->setLabel($this->t('Update'));
    }
  }

}
