#!/usr/bin/env bash

# Mute GrumPHP.
test ! -f ./vendor/bin/grumphp || ./vendor/bin/grumphp git:deinit

# Store the Joinup version.
test "$(git rev-parse --is-shallow-repository)" = "false" || git fetch --unshallow
git describe --tags 2>/dev/null | tr -d '\n' > web/VERSION.txt
JOINUP_VERSION="$(cat web/VERSION.txt)"
if [ -s web/VERSION.txt ]; then
  echo "Detected Joinup version $JOINUP_VERSION"
else
  echo "Cannot detect Joinup version!"
  exit 1;
fi

# Build the codebase.
test ! -f ./vendor/bin/run || ./vendor/bin/run toolkit:build-dev

# NPM version.
echo "Node $(node --version)"
echo "NPM $(npm --version)"

# Bring Ventuno theme dependencies.
cd web/themes/ventuno && npm ci
if [ $? != 0 ]
then
  exit 2;
fi

# Bring IOP subtheme dependencies.
cd ../iop && npm ci
if [ $? != 0 ]
then
  exit 2;
fi
cd ../../..

if [ "$1" == "install" ]; then
  npm ci;
elif [ "$1" == "update" ]; then
  npm install;
else
  echo "Wrong parameter!";
  exit 4;
fi

if [ $? != 0 ]
then
  exit 3;
fi
