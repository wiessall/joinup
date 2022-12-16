<?php

/**
 * @file
 * Comment specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_comment__field_comments__news().
 */
function ventuno_preprocess_comment__field_comments__news(array &$variables): void {
  // @todo Move the permalink computing outside the theme layer, in ISAICP-4724.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4724
  if ($permalink = _ventuno_build_permalink()) {
    $variables['permalink'] = $permalink;
  }
}
