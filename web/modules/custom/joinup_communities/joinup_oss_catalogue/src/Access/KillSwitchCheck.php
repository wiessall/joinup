<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Symfony\Component\Routing\Route;

/**
 * Access checker for _eu_oss_catalogue_check_enabled routes.
 *
 * @todo Remove this service in ISAICP-9053.
 */
final readonly class KillSwitchCheck implements AccessInterface {

  public function __construct(
    protected readonly EuOssCatalogueInterface $service,
  ) {}

  /**
   * Checks access for routes with _eu_oss_catalogue_check_enabled requirement.
   *
   * @throws \Exception
   *   When the requirement has an invalid value.
   */
  public function access(Route $route, RouteMatchInterface $routeMatch): AccessResultInterface {
    $requirement = $route->getRequirement('_eu_oss_catalogue_check_enabled');
    if ($requirement !== 'TRUE') {
      throw new \Exception("The _eu_oss_catalogue_check_enabled route requirement when present can only have 'TRUE' as value but '$requirement' was given.");
    }
    return joinup_oss_catalogue_is_enabled() ? AccessResult::allowed() : AccessResult::forbidden('EU OSS Catalogue functionality is disabled');
  }

}
