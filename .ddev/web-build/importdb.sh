#!/bin/bash

MYLOADER_OPTS="--threads 8 --max-threads-per-table 1 --max-threads-for-index-creation 4 --innodb-optimize-keys AFTER_IMPORT_PER_TABLE --purge-mode NONE --user=db --password=db --host=db --database=db --skip-definer --verbose=1 --directory /var/www/html/.ddev/db/dump"

echo "Restoring MySQL database..."
myloader ${MYLOADER_OPTS}
