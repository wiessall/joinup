<?php

declare(strict_types=1);

namespace Drupal\assessments\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access for assessment add form routes.
 */
final class AssessmentAddFormAccessCheck implements AccessInterface {

  /**
   * A custom access check for assessment add forms.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResultInterface {
    $bundle = $routeMatch->getRawParameter('assessment_type');

    return AccessResult::allowedIfHasPermission($account, "create assessment $bundle")
      ->addCacheContexts(['route']);
  }

}
