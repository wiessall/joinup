<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types=1);

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\filter\FilterFormatInterface;

/**
 * Remove embed_block filter from formats before uninstalling module.
 */
function joinup_core_post_update_200200(array &$sandbox): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'filter_format', function (FilterFormatInterface $format): bool {
    $plugin_id = 'embed_block';
    if ($format->filters($plugin_id)) {
      $format->removeFilter($plugin_id);
      return TRUE;
    }
    return FALSE;
  });
}
