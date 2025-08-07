<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

/**
 * Add a no-version suffix to invalid release aliases.
 */
function joinup_core_deploy_200600(): string {
  $paths = [
    '/node/722200',
    '/node/722202',
    '/node/722204',
    '/node/722206',
    '/node/722210',
    '/node/722212',
    '/node/722214',
    '/node/722217',
    '/node/722220',
    '/node/722222',
    '/node/720451',
    '/node/720455',
    '/node/722225',
    '/node/722226',
    '/node/722228',
    '/node/722230',
    '/node/722232',
    '/node/722234',
    '/node/722236',
    '/node/722238',
    '/node/722240',
    '/node/722242',
    '/node/722244',
    '/node/722246',
    '/node/722248',
    '/node/720466',
    '/node/720471',
    '/node/720473',
    '/node/720477',
    '/node/720485',
    '/node/720487',
    '/node/720491',
    '/node/720151',
    '/node/720153',
    '/node/722379',
    '/node/721057',
    '/node/721154',
    '/node/720160',
    '/node/720163',
    '/node/722390',
    '/node/721181',
    '/node/721182',
    '/node/721189',
    '/node/720398',
    '/node/721192',
    '/node/722399',
    '/node/720400',
    '/node/721198',
    '/node/720410',
    '/node/720418',
    '/node/720423',
    '/node/720429',
    '/node/721215',
    '/node/721217',
    '/node/721225',
    '/node/721239',
    '/node/721257',
    '/node/721259',
    '/node/721267',
    '/node/720012',
    '/node/720014',
    '/node/720017',
    '/node/721281',
    '/node/721311',
  ];

  $database = \Drupal::database();
  foreach ($paths as $path) {
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);
    $alias .= '/no-version';
    // Use direct database query to avoid creating redirects.
    $database->update('path_alias')
      ->fields(['alias' => $alias])
      ->condition('path', $path)
      ->execute();
  }

  return 'Added no-version suffix to invalid release aliases.';
}
