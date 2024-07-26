<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Symfony\Component\Routing\Route;

/**
 * Provides a route access checker for _joinup_group route requirement.
 *
 * Routes using the _joinup_group access check requirement should have paths
 * containing the {rdf_entity} route parameter. The route requirement should
 * provide one of the following values:
 * - 'TRUE': Access is granted if the {rdf_entity} route parameter is an RDF
 *   entity with bundle either 'collection' or 'solution'.
 * - 'collection': Access is granted if the {rdf_entity} route parameter is an
 *   RDF entity with bundle 'collection'.
 * - 'solution': Access is granted if the {rdf_entity} route parameter is an RDF
 *   entity with bundle 'solution'.
 * - 'FALSE': Access is granted if the {rdf_entity} route parameter is an RDF
 *   entity whose bundle is neither 'collection', nor 'solution'.
 * - For any other value, an exception is thrown.
 */
class JoinupGroupAccessCheck implements AccessInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Builds a new access checker instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access based on _joinup_group route requirement.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Exception
   *   Thrown in one of the following circumstances:
   *   - The '_joinup_group' route requirement value is not one of 'TRUE',
   *     'FALSE', 'collection' or 'solution'.
   *   - The {rdf_entity} route parameter is missed or is not an RDF entity.
   */
  public function access(Route $route, RouteMatchInterface $route_match): AccessResultInterface {
    $requirement = $route->getRequirement('_joinup_group');
    if (!in_array($requirement, ['TRUE', 'FALSE'], TRUE) && !isset(JoinupGroupHelper::GROUP_BUNDLES[$requirement])) {
      throw new \Exception("The '_joinup_group' route requirement should have one of the following values: 'TRUE', 'FALSE', 'collection' or 'solution' but '{$requirement}' was given.");
    }

    // If the node from the route is a release, we might be in a route that is
    // related to a distribution, thus, still we need to check for the solution
    // instead.
    //
    // @todo Change this in ISAICP-8572 to check for nodes but also handle
    //   collections and add a new todo for the collections ticket.
    if ($release = $route_match->getParameter('node')) {
      $group = $release->getGroup();
    }
    else {
      $group = $route_match->getParameter('rdf_entity');
    }

    // If the route wasn't configured to upcast the parameter, try to load an
    // RDF entity given its ID.
    if (!$group instanceof GroupInterface) {
      // If the {rdf_entity} route parameter is missed or is not an RDF
      // entity, looks like we have a bug.
      throw new \Exception("The {rdf_entity} route parameter is missed or is not an RDF entity.");
    }
    $bundle = $group->bundle();

    $allow_access =
      ($requirement === 'TRUE' && isset(JoinupGroupHelper::GROUP_BUNDLES[$bundle])) ||
      ($requirement === 'FALSE' && !isset(JoinupGroupHelper::GROUP_BUNDLES[$bundle])) ||
      (isset(JoinupGroupHelper::GROUP_BUNDLES[$requirement]) && $bundle === JoinupGroupHelper::GROUP_BUNDLES[$requirement]);

    /** @var \Drupal\joinup_workflow\ArchivableEntityInterface $group */
    $allow_access = $allow_access && !$group->isArchived();

    return $allow_access ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
