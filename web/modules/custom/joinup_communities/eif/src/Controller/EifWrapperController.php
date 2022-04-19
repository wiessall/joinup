<?php

declare(strict_types = 1);

namespace Drupal\eif\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides a landing page for EIF Wrapper field UI functionality.
 */
class EifWrapperController extends ControllerBase {

  /**
   * Base page for EIF Wrapper settings.
   *
   * @return array
   *   A render array.
   */
  public function content(): array {
    return [
      '#markup' => $this->t('Use tabs to manage fields, form and view displays.'),
    ];
  }

}
