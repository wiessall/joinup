<?php

declare(strict_types = 1);

namespace Drupal\joinup_like\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying a joinup like anonymous page.
 */
class JoinupLikeAnonymousController extends ControllerBase {

  /**
   * Returns a render-able array for a joinup like anonymous page.
   *
   * @return array
   *   The "joinup_like_anonymous" render array.
   */
  public function render(): array {
    return [
      '#theme' => 'joinup_like_anonymous',
    ];
  }

}
