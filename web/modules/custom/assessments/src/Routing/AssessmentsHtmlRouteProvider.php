<?php

declare(strict_types=1);

namespace Drupal\assessments\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for the Assessment entity.
 */
class AssessmentsHtmlRouteProvider extends DefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddPageRoute(EntityTypeInterface $entity_type): Route|null {
    $route = parent::getAddPageRoute($entity_type);
    if (!$route instanceof Route) {
      return $route;
    }

    $route->setDefault('_controller', '\Drupal\assessments\Controller\AssessmentAddPageController::addPage');
    return $route;
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
    $route->setRequirement('_assessment_add_form_access', 'TRUE');

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
    $route = parent::getCanonicalRoute($entity_type);
    $entity_type_id = $entity_type->id();
    if (!$route instanceof Route) {
      return $route;
    }
    $route->setOption('parameters', [
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

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type): ?Route {
    $route = parent::getEditFormRoute($entity_type);
    $entity_type_id = $entity_type->id();
    if (!$route instanceof Route) {
      return $route;
    }
    $route->setOption('parameters', [
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

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type): ?Route {
    $route = parent::getDeleteFormRoute($entity_type);
    $entity_type_id = $entity_type->id();
    if (!$route instanceof Route) {
      return $route;
    }
    $route->setOption('parameters', [
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

}
