#!/usr/bin/env bash

# Runs the Drupal Status Report. If there are errors and/or warnings, apart from
# those explicitly ignored, displays them and exits with an error code.

IGNORE_OPTION=${DRUPAL_STATUS_REPORT_IGNORE:-update status,joinup_core_root_user_blocked,search_api_solr_schema_joinup_modifications}

if [[ -n $IGNORE_OPTION ]]
then
  IGNORE_OPTION="--ignore='$IGNORE_OPTION'"
fi

status_report() {
  STATUS_REPORT_CMD="./vendor/bin/drush core:requirements --severity=1 $IGNORE_OPTION"
  if [[ -n $1 ]]
  then
    STATUS_REPORT_CMD="$STATUS_REPORT_CMD --field=title"
  fi
  eval $STATUS_REPORT_CMD
}

if [ "$(status_report title_only | wc -l)" != "0" ]
then
  echo "Status Report shows errors & warnings"
  status_report
  exit 1;
fi
