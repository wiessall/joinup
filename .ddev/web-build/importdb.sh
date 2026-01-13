#!/bin/bash

export MYSQL_HOST="db"
export MYSQL_DB_NAME="db"
export MYSQL_PWD="root"
export MYSQL_USER="root"

TIMESTAMP=$(date +%s%N)
DUMP_DIR="$1$TIMESTAMP"
MYLOADER_OPTS="--threads 8 --max-threads-per-table 1 --max-threads-for-index-creation 4 --innodb-optimize-keys AFTER_IMPORT_PER_TABLE --purge-mode NONE --user=$MYSQL_USER --password=$MYSQL_PWD --host=$MYSQL_HOST --database=$MYSQL_DB_NAME --skip-definer --verbose=1 --directory $DUMP_DIR"

echo "Preparing database dump..."
mkdir -p $DUMP_DIR
tar -C $DUMP_DIR -xf $1

echo "Restoring database..."
/usr/bin/myloader ${MYLOADER_OPTS}

echo "Post-restore cleanup..."
rm -rf $DUMP_DIR

echo "Refreshing table statistics..."
tables_csv=$(mysql $MYSQL_DB_NAME -Nse "SHOW TABLES;" | paste -sd "," -)
mysql $MYSQL_DB_NAME -e "ANALYZE TABLE $tables_csv;" >/dev/null
