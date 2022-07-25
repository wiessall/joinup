#!/usr/bin/env php
<?php

/**
 * @file
 * Runs the Drupal Status Report.
 *
 * If there are errors and/or warnings, apart from those explicitly ignored,
 * displays them and exits with an error code.
 */

if (PHP_SAPI !== 'cli') {
  return;
}

$autoloader = require __DIR__ . '/../vendor/autoload.php';

$ignored_requirements = getenv('DRUPAL_STATUS_REPORT_IGNORE') ?: 'update status,joinup_core_root_user_blocked,search_api_solr_schema_joinup_modifications';
$ignored_option = "--ignore='$ignored_requirements'";

function run_status_report(bool $title_only): ?string {
  global $ignored_option;
  $command = __DIR__ . "/../vendor/bin/drush core:requirements --severity=1 $ignored_option ";
  $command .= $title_only ? '--field=title' : '--fields=title,severity,value,description';
  return shell_exec($command);
}

if (run_status_report(TRUE) !== NULL) {
  print "Status Report shows errors & warnings\n";
  print run_status_report(FALSE) . "\n";
  exit(1);
}
