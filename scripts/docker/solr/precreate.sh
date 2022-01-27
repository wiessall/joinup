#!/bin/bash

precreate-core ${SOLR_CORE} /solr
if [ "${DOCKER_RESTORE_PRODUCTION}" = "yes" ]; then
  /opt/solr/bin/solr start
  sleep 5
  /usr/bin/curl -sS "http://localhost:8983/solr/${SOLR_CORE}/replication?command=restore&name=joinup&location=/solr/snapshot&wt=xml"
  (until [[ "$(/usr/bin/curl -sS ''http://localhost:8983/solr/${SOLR_CORE}/replication?command=restorestatus'')" =~ \>success\< ]]; do sleep 1; done)
  /opt/solr/bin/solr stop
fi
