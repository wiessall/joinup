<?php

declare(strict_types=1);

namespace Drupal\assessments\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides HTML routes for the Assessment entity.
 */
class AssessmentsHtmlRouteProvider extends DefaultHtmlRouteProvider {

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
          $entity_type_id => [
            'type' => 'entity:' . $entity_type_id,
            'bundle' => ['report'],
          ],
        ]);

      return $route;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type): Route|null {
    $route = parent::getAddFormRoute($entity_type);

    if (!$route instanceof Route) {
      return $route;
    }

    $route->setDefault('_title_callback', [self::class, 'addBundleTitle']);

    return $route;
  }

  /**
   * Provides an add title callback for assessment bundles.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_parameter
   *   The name of the route parameter that holds the bundle.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the entity add page.
   */
  public static function addBundleTitle(RouteMatchInterface $route_match, string $entity_type_id, string $bundle_parameter): TranslatableMarkup {
    $bundles = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo($entity_type_id);
    $bundle = $route_match->getRawParameter($bundle_parameter);
    return t('Create @bundle', ['@bundle' => $bundles[$bundle]['label_singular']]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('canonical')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('canonical'));
      $route
        ->addDefaults([
          '_entity_view' => "{$entity_type_id}.full",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
          'assessment_type' => NULL,
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.view")
        ->setOption('parameters', [
          $entity_type_id => [
            'type' => 'entity:' . $entity_type_id,
          ],
          'assessment_type' => [
            'type' => 'entity:assessment_type',
            'with_config_overrides' => TRUE,
          ],
        ]);

      return $route;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('edit-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('edit-form'));
      $route
        ->setDefaults([
          '_entity_form' => "{$entity_type_id}.edit",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::editTitle',
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.update")
        ->setOption('parameters', [
          $entity_type_id => [
            'type' => 'entity:' . $entity_type_id,
          ],
          'assessment_type' => [
            'type' => 'entity:assessment_type',
            'with_config_overrides' => TRUE,
          ],
        ]);

      return $route;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('delete-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('delete-form'));
      $route
        ->setDefaults([
          '_entity_form' => "{$entity_type_id}.delete",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::deleteTitle',
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.delete")
        ->setOption('parameters', [
          $entity_type_id => [
            'type' => 'entity:' . $entity_type_id,
          ],
          'assessment_type' => [
            'type' => 'entity:assessment_type',
            'with_config_overrides' => TRUE,
          ],
        ]);

      return $route;
    }

    return NULL;
  }

}
