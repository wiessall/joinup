#!/bin/bash

set -x

precreate-core ${SEARCH_API_SERVER_SS_SOLR_CORE} /solr
if [ "${DOCKER_RESTORE_PRODUCTION}" = "yes" ]
then
  echo "Restoring Solr core from production snapshot."
  /opt/solr/bin/solr start -Dsolr.allowPaths=/solr/snapshot
  sleep 5
  RESTORE_COMMAND="http://localhost:8983/solr/${SEARCH_API_SERVER_SS_SOLR_CORE}/replication?command="
  /usr/bin/curl -sS "${RESTORE_COMMAND}restore&name=${SEARCH_API_SERVER_SS_SOLR_CORE}&location=/solr/snapshot"
#  RESTORE_STATUS_OUTPUT=""
  until [[ "${RESTORE_STATUS_OUTPUT}" =~ '"status":"success"' ]]
  do
    sleep 1
    RESTORE_STATUS_OUTPUT=$(/usr/bin/curl -sS "${RESTORE_COMMAND}restorestatus")
  done
  /opt/solr/bin/solr stop
  echo "Restored Solr core from production snapshot."
else
  echo "Installed an empty Solr core."
fi
