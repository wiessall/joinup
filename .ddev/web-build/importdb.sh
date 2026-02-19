#!/bin/bash
# This is adapted from https://git.fpfis.tech.ec.europa.eu/digit/devsecops/httpd-php/-/blob/master/scripts/myloader.sh
set -euo pipefail

export MYSQL_HOST="db"
export MYSQL_DB_NAME="db"
export MYSQL_PWD="root"
export MYSQL_USER="root"

echo "Preparing database dump..."
TIMESTAMP=$(date +%s%N)
DUMP_DIR="$1$TIMESTAMP"
mkdir -p "$DUMP_DIR"
tar -C "$DUMP_DIR" -xf "$1"

echo "Restoring database..."
MYLOADER_OPTS=(
  --threads "${MYLOADER_THREADS:-8}"
  --max-threads-per-table "${MYLOADER_MAX_THREADS_TABLE:-1}"
  --max-threads-for-index-creation "${MYLOADER_MAX_THREADS_CREATION:-4}"
  --optimize-keys AFTER_IMPORT_PER_TABLE
  --purge-mode NONE
  --skip-definer
  --skip-triggers
  --verbose=2
  --directory "$DUMP_DIR"
  --user="$MYSQL_USER"
  --password="$MYSQL_PWD"
  --host="$MYSQL_HOST"
  --database="$MYSQL_DB_NAME"
)
if ! myloader "${MYLOADER_OPTS[@]}"; then
  echo "ERROR: myloader failed!"
  exit 1
fi

echo "Post-restore cleanup..."
rm -rf "$DUMP_DIR"

echo "Refreshing table statistics..."
tables_csv=$(mysql $MYSQL_DB_NAME -Nse "SHOW TABLES;" | paste -sd "," -)
mysql $MYSQL_DB_NAME -e "ANALYZE TABLE $tables_csv;" >/dev/null
