#!/usr/bin/env php
<?php

// This script runs only from console.
if (PHP_SAPI !== 'cli') {
  return;
}

$projectRoot = realpath(__DIR__ . '/../..');
require_once "$projectRoot/vendor/autoload.php";
$exitCode = 1;

if ($argc !== 2 || !in_array($argv[1], ['install', 'update'], TRUE)) {
  print "The command should be used with strictly one parameter: 'install' and 'update'.\n";
  exit($exitCode++);
}

// Store the Joinup version.
print "Detecting the Joinup version... ";
if (execCmd('git rev-parse --is-shallow-repository') !== 'false') {
  // It's a shallow repo.
  execCmd('git fetch --unshallow');
}
$versioFile = "$projectRoot/web/VERSION.txt";
execCmd("git describe --tags 2>/dev/null | tr -d '\n' > $versioFile");
$joinupVersion = trim(file_get_contents($versioFile));
if (!$joinupVersion) {
  print "cannot detect Joinup version!\n";
  exit($exitCode++);
}
print "detected Joinup version $joinupVersion\n";

// Build the codebase.
if (file_exists("$projectRoot/vendor/bin/run")) {
  execCmd("$projectRoot/vendor/bin/run toolkit:build-dev");
}

if (!getenv('COMPOSER_DISABLE_NODE_DEPENDENCIES')) {
  // Node/NPM version.
  print 'Node ' . execCmd("node --version") . "\n";
  print 'NPM ' . execCmd("npm --version") . "\n";

  // Theme dependencies.
  $command = "npm ci --prefix web/themes/ventuno --no-progress";
  print "Running: $command\n";
  execCmd($command);

  // Bring IOP subtheme dependencies.
  $command = "npm ci --prefix web/themes/iop --no-progress";
  print "Running: $command\n";
  execCmd($command);

  // Project dependencies.
  $command = $argv[1] === 'install' ? 'npm ci' : 'npm install';
  print "Running: $command --no-progress\n";
  execCmd("$command --no-progress");
}

/**
 * Executes a shell command and exits the script on failure.
 *
 * @param string $command
 *   The command to be executed.
 *
 * @return mixed
 *   The last line of the command execution output.
 */
function execCmd(string $command) {
  global $exitCode;
  $result = exec($command);
  if ($result === FALSE) {
    exit($exitCode);
  }
  $exitCode++;
  return $result;
}
