#!/usr/bin/env bash

# Mute GrumPHP.
test ! -f ./vendor/bin/grumphp || ./vendor/bin/grumphp git:deinit

# NPM version.
echo "Node $(node --version)"
echo "NPM $(npm --version)"

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
