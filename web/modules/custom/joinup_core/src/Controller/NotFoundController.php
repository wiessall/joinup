<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Builds a custom 404 page with a simple text.
 */
class NotFoundController extends ControllerBase {

  /**
   * Constructs a 404 page.
   *
   * @return array
   *   The render array.
   */
  public function build404(): array {
    return [
      '#theme' => '404_not_found',
    ];
  }

}
