#!/bin/sh

if [ "${DOCKER_RESTORE_PRODUCTION}" = "yes" ]; then
  BACKUP_PREFIX="VIRTUOSO_DUMP_" /bin/bash /virtuoso.sh
else
  /bin/bash /virtuoso.sh
fi
