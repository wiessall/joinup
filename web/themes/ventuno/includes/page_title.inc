<?php

/**
 * @file
 * Page title specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_page_title().
 */
function ventuno_preprocess_page_title(array &$variables): void {
  // @todo Move the permalink computing outside the theme layer, in ISAICP-4724.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4724
  if ($permalink = _ventuno_build_permalink()) {
    $variables['permalink'] = $permalink;
  }
}
