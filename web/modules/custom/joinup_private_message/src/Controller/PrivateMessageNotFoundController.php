<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for handling 404 responses.
 */
class PrivateMessageNotFoundController {

  /**
   * Returns a 404 response.
   *
   * @return void
   *   The response.
   */
  public function notFound(): void {
    throw new NotFoundHttpException();
  }

}
