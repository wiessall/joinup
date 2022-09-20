#!/usr/bin/env php
<?php

use Gitonomy\Git\Repository;

if (PHP_SAPI !== 'cli') {
  return;
}

if (count($argv) > 2) {
  print "The command accepts only one parameter.\n";
  exit(1);
}

if (isset($argv[1]) && !in_array($argv[1], ['install', 'update'], TRUE)) {
  print "Only 'install' and 'update' are valid options for command argument.\n";
  exit(1);
}

$project_root = realpath(__DIR__ . '/../..');
require_once "$project_root/vendor/autoload.php";

// Mute GrumPHP.
if (file_exists("$project_root/vendor/bin/grumphp") && (system("$project_root/vendor/bin/grumphp git:deinit") === FALSE)) {
  exit(2);
}

// Store the Joinup version.
print "Getting the Joinup version...\n";
$git = new Repository($project_root);
if (trim((string) $git->run('rev-parse', ['--is-shallow-repository'])) !== 'false') {
  $git->run('fetch', ['--unshallow']);
  if ($version = trim($git->run('describe', ['--tags']))) {
    file_put_contents("$project_root/web/VERSION.txt", $version);
    print "Detected Joinup version $version\n";
  }
  else {
    print "Cannot detect Joinup version!\n";
    exit(3);
  }
}

// Build the codebase.
if (file_exists("$project_root/vendor/bin/run") && (system("$project_root/vendor/bin/run toolkit:build-dev") === FALSE)) {
  exit(4);
}

if (!getenv('COMPOSER_DISABLE_NODE_DEPENDENCIES')) {
  // Node/NPM version.
  print 'Node ' . trim(shell_exec("node --version")) . "\n";
  print 'NPM ' . trim(shell_exec("npm --version")) . "\n";

  // Bring theme dependencies.
  $command = "cd $project_root/web/themes/ventuno && npm ci";
  print "Running: $command\n";
  if (system($command) === FALSE) {
    exit(5);
  }

  // Project dependencies.
  $command = $argv[1] === 'install' ? 'npm ci' : 'npm install';
  $command = "cd $project_root && $command";
  print "Running: $command\n";
  if (system($command) === FALSE) {
    exit(5);
  }
}
