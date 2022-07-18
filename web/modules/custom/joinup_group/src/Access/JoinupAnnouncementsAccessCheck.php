<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\message\MessageInterface;
use Drupal\og\OgAccessInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides a route access checker for _entity_access route requirement.
 *
 * Disallow edit sent messages when they are in sent state.
 */
class JoinupAnnouncementsAccessCheck implements AccessInterface {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The OG access service.
   */
  protected OgAccessInterface $ogAccess;

  /**
   * Builds a new access checker instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\og\OgAccessInterface $ogAccess
   *   The OG access service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, OgAccessInterface $ogAccess) {
    $this->entityTypeManager = $entity_type_manager;
    $this->ogAccess = $ogAccess;
  }

  /**
   * Checks access based on _entity_access route requirement.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match): AccessResultInterface {
    if ($route->getRequirement('_entity_access') === 'message.edit' || $route->getRequirement('_entity_access') === 'message.view') {
      $message = $route_match->getParameter('message');
      if ($message instanceof MessageInterface) {
        /** @var \Drupal\joinup_group\Entity\GroupContentInterface $group */
        $group = $this->entityTypeManager->getStorage('rdf_entity')
          ->load($message->get('group_reference')->target_id);

        $access = $this->ogAccess->userAccess($group, 'manage members')->isAllowed();
        // Check if message was already sent.
        if ($route->getRequirement('_entity_access') === 'message.edit') {
          $access = $access && $message->get('state')->value !== 'sent';
        }

        return AccessResult::allowedIf($access)
          ->addCacheableDependency($message)
          ->addCacheableDependency($group);
      }
    }
    return AccessResult::allowed();
  }

}
