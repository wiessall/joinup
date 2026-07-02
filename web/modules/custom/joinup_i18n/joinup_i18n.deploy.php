<?php

/**
 * @file
 * Deploy hooks for the Joinup i18n module.
 */

declare(strict_types=1);

use Drupal\joinup_i18n\Update\TmgmtDataRepair;

/**
 * Repairs field values that tmgmt translated and mangled.
 */
function joinup_i18n_deploy_201501(): void {
  \Drupal::classResolver(TmgmtDataRepair::class)->run();
}
