<?php

declare(strict_types=1);

namespace Drupal\joinup_poll\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Ajax request access check.
 */
class AjaxRequestAccessCheck implements AccessInterface {

  /**
   * Performs access check.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   Route .
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access result
   */
  public function access(Route $route, Request $request): AccessResult {
    $ajaxRequirement = filter_var($route->getRequirement('_ajax_request'), FILTER_VALIDATE_BOOLEAN);
    $isAjax = $request->isXmlHttpRequest();

    if ($ajaxRequirement === $isAjax) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
