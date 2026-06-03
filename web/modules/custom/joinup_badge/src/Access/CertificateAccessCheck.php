<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;

/**
 * Check access to certificates.
 */
final class CertificateAccessCheck implements AccessInterface {

  /**
   * Check access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\node\NodeInterface|null $node
   *   Certificate entity.
   */
  public function access(Route $route, ?NodeInterface $node = NULL): AccessResult {
    if (
      $node instanceof NodeInterface &&
      $node->bundle() === 'certificate'
    ) {
      throw new NotFoundHttpException();
    }

    return AccessResult::neutral();
  }

}
