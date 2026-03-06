<?php

declare(strict_types=1);

namespace Drupal\assessments\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks assessment asset creation access route.
 */
final class AssessmentAssetCreationAccessCheck implements AccessInterface {

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account): AccessResultInterface {
    $requirement = $route->getRequirement('_assessments_asset_creation_access');
    if ($requirement !== 'TRUE') {
      throw new \Exception("The '_assessments_asset_creation_access' route requirement should be 'TRUE', but '$requirement' was given.");
    }

    return AccessResult::allowed();
  }

}
