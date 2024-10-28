<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\EventSubscriber;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Event\PermissionEventInterface as OgPermissionEventInterface;
use Drupal\og\GroupContentOperationPermission;
use Drupal\og\GroupPermission;
use Drupal\og\OgAccessInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscribers for the Interoperable Europe Portal community content module.
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
    OgAccessInterface $og_access,
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
      OgPermissionEventInterface::EVENT_NAME => [['provideOgRevisionPermissions']],
    ];
  }

  /**
   * Declare OG permissions for handling revisions.
   *
   * @param \Drupal\og\Event\PermissionEventInterface $event
   *   The OG permission event.
   */
  public function provideOgRevisionPermissions(OgPermissionEventInterface $event): void {
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

}
