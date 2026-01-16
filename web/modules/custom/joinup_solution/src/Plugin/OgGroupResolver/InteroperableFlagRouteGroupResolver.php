<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\OgGroupResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flag\FlagInterface;
use Drupal\og\Attribute\OgGroupResolver;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;
use Symfony\Component\Routing\Route;

/**
 * Resolves the group for the interoperable flag.
 */
#[OgGroupResolver(
  id: 'interoperable_flag_routes',
  label: new TranslatableMarkup('Routes related to interoperable flag'),
  description: new TranslatableMarkup('Routes related to interoperable flag')
)]
class InteroperableFlagRouteGroupResolver extends RouteGroupResolver {

  /**
   * {@inheritdoc}
   */
  protected function getContentEntity(): ?EntityInterface {
    $route = $this->routeMatch->getRouteObject();
    if (!$route || !$this->isInteroperableFlag($route)) {
      return NULL;
    }

    return $this->loadEntity();
  }

  /**
   * Checks if it is the interoperable flag route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   Route.
   *
   * @return bool
   *   True if is it the interoperable route
   */
  protected function isInteroperableFlag(Route $route): bool {
    if (!in_array($route->getPath(), [
      '/flag/unflag/{flag}/{entity_id}/{view_mode}',
      '/flag/flag/{flag}/{entity_id}/{view_mode}',
    ], TRUE)) {
      return FALSE;
    }

    $flag = $this->routeMatch->getParameter('flag');
    if ($flag instanceof FlagInterface || $flag->id() === 'interoperability') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Loads entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Content entity.
   */
  public function loadEntity(): ?EntityInterface {
    $entityId = $this->routeMatch->getParameter('entity_id');
    if (!$entityId) {
      return NULL;
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    return $nodeStorage->load($entityId);
  }

}
