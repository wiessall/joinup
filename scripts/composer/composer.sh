#!/usr/bin/env bash

set -x

# Mute GrumPHP.
test ! -f ./vendor/bin/grumphp || ./vendor/bin/grumphp git:deinit

# Store the Joinup version.
test "$(git rev-parse --is-shallow-repository)" = "false" || git fetch --unshallow
test "$(git tag)" = "" || git describe --tags | tr -d '\n' > web/VERSION.txt

# Build the codebase.
test ! -f ./vendor/bin/run || ./vendor/bin/run toolkit:build-dev

# Bring theme dependencies.
cd web/themes/ventuno && npm ci

cd ../../..

if [ "$1" == "install" ]; then
  npm ci;
elif [ "$1" == "update" ]; then
  npm install;
else
  echo "Wrong parameter!";
  exit 1;
fi
