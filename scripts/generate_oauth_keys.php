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
 * Generate OAuth keys using Drush eval.
 */
function generate_oauth_keys(): void {
  $drush = __DIR__ . '/../vendor/bin/drush';

  // Create a temporary PHP file with the code to execute.
  $temp_file = tempnam(sys_get_temp_dir(), 'oauth_keys_');
  $php_code = <<<'PHP'
<?php
$fileSystem = \Drupal::service("file_system");
$directory = "private://simple_oauth";
$privateKeyPath = $fileSystem->realpath("private://simple_oauth/private.key");

if (!$privateKeyPath || !file_exists($privateKeyPath)) {
  $fileSystem->prepareDirectory(
    $directory,
    \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY |
    \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
  );

  $keygen = \Drupal::service("simple_oauth.key.generator");
  $keygen->generateKeys($fileSystem->realpath($directory));

  echo "OAUTH_SUCCESS: OAuth keys generated in GitLab CI.\n";
} else {
  echo "OAUTH_SUCCESS: OAuth keys already exist — skipping.\n";
}
PHP;

  file_put_contents($temp_file, $php_code);

  $drush_command = sprintf('%s php:script %s 2>&1', escapeshellarg($drush), escapeshellarg($temp_file));
  $output = shell_exec($drush_command);

  // Clean up temp file.
  unlink($temp_file);

  if ($output !== null) {
    // Extract only the success messages.
    if (preg_match('/OAUTH_SUCCESS: (.+)/', $output, $matches)) {
      echo $matches[1] . "\n";
    }
  }
}

if (is_gitlab_ci()) {
  echo "Running in GitLab CI — generating OAuth keys if missing...\n";
  generate_oauth_keys();
} else {
  echo "Not running in GitLab CI — skipping OAuth key generation.\n";
}
