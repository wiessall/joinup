#!/usr/bin/env php
<?php

/**
 * @file
 * Runs the Drupal Status Report.
 *
 * If there are errors and/or warnings, apart from those explicitly ignored,
 * displays them and exits with an error code.
 *
 * phpcs:ignoreFile
 */

if (PHP_SAPI !== 'cli') {
  return;
}

$autoloader = require __DIR__ . '/../vendor/autoload.php';

function run_status_report(bool $title_only, bool $display_command = FALSE): ?string {
  $command = __DIR__ . '/../vendor/bin/drush core:requirements --severity=1';
  $command .= $title_only ? ' --field=title' : ' --fields=title,severity,value,description --format=sections';
  if ($display_command) {
    print $command . "\n";
  }
  return shell_exec($command);
}

if (run_status_report(TRUE) !== NULL) {
  print "Status Report shows errors & warnings\n";
  print run_status_report(FALSE, TRUE) . "\n";
  exit(1);
}
