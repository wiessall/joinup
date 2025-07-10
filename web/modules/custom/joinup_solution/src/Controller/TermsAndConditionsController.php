<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Controller;

use Drupal\joinup_group\Controller\TermsAndConditionsControllerBase;

/**
 * A simple page that presents a TCA for the solution creation.
 */
class TermsAndConditionsController extends TermsAndConditionsControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getTcaBlockId(): string {
    return 'simple_block:solution_tca';
  }

}
