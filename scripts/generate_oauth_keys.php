#!/usr/bin/env php
<?php

/**
 * @file
 * Generate OAuth keys if they don't exist (only in GitLab CI).
 *
 * This script is sourced from post_install.
 *
 * phpcs:ignoreFile
 */

if (PHP_SAPI !== 'cli') {
  return;
}

$autoloader = require __DIR__ . '/../vendor/autoload.php';

/**
 * Check if running in GitLab CI environment.
 */
function is_gitlab_ci(): bool {
  return getenv('GITLAB_CI') === 'true';
}

/**
 * Generate OAuth keys using Drush command.
 */
function generate_oauth_keys(): void {
  $drush = __DIR__ . '/../vendor/bin/drush';
  $drush_command = sprintf('%s joinup:generate-oauth-keys 2>&1', escapeshellarg($drush));
  $output = shell_exec($drush_command);

  if ($output !== null) {
    echo $output;
  }
}

if (is_gitlab_ci()) {
  echo "Running in GitLab CI — generating OAuth keys if missing...\n";
  generate_oauth_keys();
} else {
  echo "Not running in GitLab CI — skipping OAuth key generation.\n";
}
