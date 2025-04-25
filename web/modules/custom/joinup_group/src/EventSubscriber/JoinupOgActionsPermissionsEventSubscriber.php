<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\JoinupGroupPermissionManagerInterface;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines module event subscriber class.
 *
 * Alters actions to make use of OG permissions created by the module.
 */
final class JoinupOgActionsPermissionsEventSubscriber implements EventSubscriberInterface {

  // Subscribe to the VBO event with low priority
  // to let other modules alter requirements first.
  private const PRIORITY = -999;

  private const PERMISSIONS = [
    'joinup_archive' => 'vbo joinup archive action',
    'joinup_unarchive' => 'vbo joinup unarchive action',
    'joinup_change_group' => 'vbo joinup change group action',
  ];

  /**
   * Constructor.
   */
  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly RouteMatchInterface $currentRouteMatch,
    #[Autowire(service: 'Drupal\joinup_group\JoinupGroupPermissionManagerInterface')]
    protected readonly JoinupGroupPermissionManagerInterface $joinupGroupManagerPermissions,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ViewsBulkOperationsActionManager::ALTER_ACTIONS_EVENT][] = [
      'alterActions',
      self::PRIORITY,
    ];
    return $events;
  }

  /**
   * Alter the actions' definitions.
   *
   * @var \Drupal\Component\EventDispatcher\Event $event
   *   The event to respond to.
   */
  public function alterActions(Event $event): void {
    $collection = $this->currentRouteMatch->getParameter('node');
    if ($collection instanceof EntityInterface) {
      foreach ($event->definitions as $action_id => $definition) {
        // Only process actions that don't define their own requirements.
        if (empty($definition['requirements'])) {
          if (array_key_exists($definition['id'], self::PERMISSIONS)) {
            $permission_id = self::PERMISSIONS[$definition['id']];
            if (!$this->currentUser->hasPermission($permission_id) && !$this->joinupGroupManagerPermissions->userHasOgCollectionPermission($this->currentUser->id(), $collection, $permission_id)) {
              unset($event->definitions[$action_id]);
            }
          }
        }
      }
    }
  }

}
