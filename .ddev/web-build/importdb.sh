#!/bin/bash

TIMESTAMP=$(date +%s%N)
DUMP_DIR="$1$TIMESTAMP"
MYLOADER_OPTS="--threads 8 --max-threads-per-table 1 --max-threads-for-index-creation 4 --innodb-optimize-keys AFTER_IMPORT_PER_TABLE --purge-mode NONE --user=db --password=db --host=db --database=db --skip-definer --verbose=1 --directory $DUMP_DIR"

echo "Preparing database dump..."
mkdir -p $DUMP_DIR
tar -C $DUMP_DIR -xf $1

echo "Restoring database..."
/usr/bin/myloader ${MYLOADER_OPTS}

echo "Post-restore cleanup..."
rm -rf $DUMP_DIR
