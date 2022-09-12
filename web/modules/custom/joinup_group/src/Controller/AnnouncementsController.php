<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\views\Views;

/**
 * Returns responses for the group announcement form.
 */
class AnnouncementsController extends ControllerBase {

  /**
   * Display a list of announcements that belong to the group.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   *
   * @return array
   *   The view containing the members overview, as a renderable array.
   */
  public function announcementsList(RouteMatchInterface $route_match): array {
    $group_entity_type_id = $route_match->getRouteObject()->getOption('_og_entity_type_id');
    /** @var \Drupal\Core\Entity\EntityInterface $group */
    $group = $route_match->getParameter($group_entity_type_id);

    return Views::getView('announcements_list')->executeDisplay('default', [$group->id()]);
  }

  /**
   * Access check for the group announcement form.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group for which the access to the reports page is being determined.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(GroupInterface $rdf_entity): AccessResultInterface {
    $meta_entity = MetaEntity::loadOrCreate($this->getMetaEntityBundle($rdf_entity), $rdf_entity);
    return AccessResult::allowedIf((bool) $meta_entity->announcements_enabled->value)->addCacheTags(['meta_entity_list:' . $this->getMetaEntityBundle($rdf_entity)]);
  }

  /**
   * Format a meta entity bundle ID.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group where to show settings.
   *
   * @return string
   *   The meta entity bundle ID.
   */
  private function getMetaEntityBundle(GroupInterface $rdf_entity): string {
    return "{$rdf_entity->bundle()}_settings";
  }

}
