#!/usr/bin/env php
<?php

/**
 * @file
 * Run through PHP to get advantage of phpdotenv.
 */

// This script runs only from console.
if (PHP_SAPI === 'cli') {
  $projectRoot = realpath(__DIR__ . '/../..');
  require_once "$projectRoot/vendor/autoload.php";
  exit((int) empty(getenv('COMPOSER_DISABLE_NODE_DEPENDENCIES')));
}

exit(2);
