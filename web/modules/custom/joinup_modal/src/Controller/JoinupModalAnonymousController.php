<?php

declare(strict_types = 1);

namespace Drupal\joinup_modal\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying a joinup modal anonymous page.
 */
class JoinupModalAnonymousController extends ControllerBase {

  /**
   * Returns a render-able array for a joinup modal anonymous page.
   *
   * @param string $type
   *   Modal type.
   *
   * @return array
   *   The "joinup_modal_anonymous" render array.
   */
  public function render(string $type): array {
    return [
      '#theme' => 'joinup_modal_anonymous',
      '#type' => $type,
      '#title' => $this->t('Login or register'),
    ];
  }

}
