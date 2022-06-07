#!/usr/bin/env bash

# Mute GrumPHP.
test ! -f ./vendor/bin/grumphp || ./vendor/bin/grumphp git:deinit

# NPM version.
echo "Node $(node --version)"
echo "NPM $(npm --version)"

# Bring theme dependencies.
cd web/themes/ventuno && npm ci
if [ $? != 0 ]
then
  exit 1;
fi
cd ../../..

if [ "$1" == "install" ]; then
  npm ci;
elif [ "$1" == "update" ]; then
  npm install;
else
  echo "Wrong parameter!";
  exit 3;
fi

if [ $? != 0 ]
then
  exit 2;
fi
