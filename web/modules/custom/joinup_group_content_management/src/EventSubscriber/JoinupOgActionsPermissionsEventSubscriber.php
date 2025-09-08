<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\ContextProvider\OgContext;
use Drupal\views_bulk_operations\ActionAlterDefinitionsEvent;
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
  private const int PRIORITY = -999;

  private const array PERMISSIONS = [
    'joinup_archive' => 'vbo joinup archive action',
    'joinup_unarchive' => 'vbo joinup unarchive action',
    'joinup_change_group' => 'vbo joinup change group action',
  ];

  /**
   * Constructor.
   */
  public function __construct(
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'og.context')]
    protected readonly OgContext $ogContext,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ViewsBulkOperationsActionManager::ALTER_ACTIONS_EVENT][] = [
      'alterActions',
      self::PRIORITY,
    ];
    return $events;
  }

  /**
   * Alter the actions' definitions.
   *
   * @param \Drupal\views_bulk_operations\ActionAlterDefinitionsEvent $event
   *   The event to respond to.
   */
  public function alterActions(ActionAlterDefinitionsEvent $event): void {
    $group = $this->ogContext->getGroup();
    foreach ($event->definitions as $action_id => $definition) {
      if (array_key_exists($definition['id'], self::PERMISSIONS) && $group instanceof GroupInterface) {
        $permission_id = self::PERMISSIONS[$definition['id']];
        $group_access = $group->hasGroupPermission($this->currentUser->id(), $permission_id);
        if (!$this->currentUser->hasPermission($permission_id) && $group_access->isForbidden()) {
          unset($event->definitions[$action_id]);
        }
      }
    }
  }

}
