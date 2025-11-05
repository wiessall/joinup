<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for the assessment entity type.
 */
final class AssessmentHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type): RouteCollection {
    $collection = parent::getRoutes($entity_type);

    if ($moderate_form_route = $this->getModerateFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type->id()}.moderate_form", $moderate_form_route);
    }

    return $collection;
  }

  /**
   * Gets the moderate-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getModerateFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('moderate-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('moderate-form'));

      $route
        ->addDefaults([
          '_entity_form' => "{$entity_type_id}.moderate",
          '_title' => 'Moderate',
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.moderate")
        ->setOption('_admin_route', TRUE)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }

    return NULL;
  }

}
