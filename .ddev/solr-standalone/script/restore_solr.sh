#!/bin/bash

# Restores a backup of the Solr data.

# Defaults
SOLR_SERVER_URL="http://localhost:8983/solr"
TIMEOUT=120

# Displays help options.
function show_help {
  echo -e "Usage:   restore_solr.sh [OPTIONS]"
  echo -e "Example: restore_solr.sh --core core0 --snapshot-dir /tmp --snapshot-name core0bak"
  echo -e "Restores a Solr core from a named snapshot located in a directory"
  echo -e "-h, --help          Show this help text"
  echo -e "-c, --core          Solr core to be restored"
  echo -e "-d, --snapshot-dir  The directory containing the snapshot"
  echo -e "-n, --snapshot-name The snapshot name"
  echo -e "-e, --solr-url      Solr server URL. Default http://localhost:8983/solr"
  echo -e "-t, --timeout       Restore time timeout. Default 120s"
  echo -e "-v, --verbose       If set, verbose logging is on"
}

# Shows a verbose message.
function log {
  if [ "${VERBOSE_LOG}" == "yes"  ]; then
    echo "$(tput setaf 3)$1$(tput sgr0)"
    echo " "
  fi
}

# Exists with a message and an error.
function error {
  echo "$(tput setaf 1)$1$(tput sgr0)";
  exit 1;
}

# Check command line arguments.
while [[ "$1" == -* ]]; do
  case "$1" in
    -h|--help|-\?) show_help; exit 0;;
    -c|--core) shift; CORE=$1; shift;;
    --) shift; break;;
    -v|--verbose) shift; VERBOSE_LOG="yes";;
    -t|--timeout) shift; TIMEOUT=$1; shift;;
    -d|--snapshot-dir) shift; SNAPSHOT_DIR=$1; shift;;
    -n|--snapshot-name) shift; SNAPSHOT_NAME=$1; shift;;
    -u|--solr-url) shift; SOLR_SERVER_URL=$1; shift;;
    -*) echo "invalid option: $1" 1>&2; show_help; exit 1;;
  esac
done

if ! [[ $TIMEOUT =~ ^[0-9]+$ ]] ; then
  error "Timeout needs to be numeric!";
fi

if [ "${CORE}" == '' ] || [ "${SNAPSHOT_DIR}" == '' ] || [ "${SNAPSHOT_NAME}" == '' ]; then
  show_help;
  exit 1;
fi

if [ ! -z "${SNAPSHOT_DIR%%/*}" ] || ! pathchk -p "${SNAPSHOT_DIR}"; then
  error "The --snapshot-dir path should be absolute!"
fi

CORE_EXISTS=$(curl -sS "${SOLR_SERVER_URL}/admin/cores?action=STATUS&core=${CORE}&wt=xml" |grep -o '<long name="uptime">')

if [ "${CORE_EXISTS}" == '' ]; then
  error "Solr '${CORE}' core does not exists on this server!";
fi

# Wipe out the existing index.
echo "Clearing '${CORE}' core exiting index"
WIPE_INDEX=$(/usr/bin/curl -sS --request POST --header "Content-Type: application/xml" --data "<delete><query>*:*</query></delete>" "${SOLR_SERVER_URL}/${CORE}/update?commit=true&wt=xml")
log "${WIPE_INDEX}"

# Restore the index.
echo -n "Restoring '${CORE}' core index [|]"
line="${SOLR_SERVER_URL}/${CORE}/replication?command=restore&name=${SNAPSHOT_NAME}&location=${SNAPSHOT_DIR}&wt=xml"
RESTORE_START=$(/usr/bin/curl -sS ${line})
log "${RESTORE_START}$(tput sgr0)"

RESTORE_START_CHECK=$(echo ${RESTORE_START} | grep -o '<str name="status">OK</str>')

if [ "${RESTORE_START_CHECK}" == '' ]; then
  error "Restore failed to start! Please check the output with -v";
fi

SUCCESS=""
RETRIES=1
SPINNER="/-\|"
START_TIME="$(date +%s)"

# Hide the cursor
tput civis -- invisible

while [[ "${SUCCESS}" != '<str name="status">success</str>' ]]
do
  ## Check whether the timeout exceeded
  CURRENT_TIME="$(date +%s)"
  ELAPSED=$(("$CURRENT_TIME - $START_TIME"))
  if [ ${ELAPSED} -ge ${TIMEOUT} ]; then
    # Restore cursor
    tput cnorm -- normal
    echo
    error "Timeout of ${TIMEOUT} seconds exceeded"
  fi

  # Wait a little
  sleep 0.25

  test $RETRIES -gt 1 || printf "\b"
  CHECK_SUCCESS=$(/usr/bin/curl -sS "${SOLR_SERVER_URL}/${CORE}/replication?command=restorestatus&name=${SNAPSHOT_NAME}&location=${SNAPSHOT_DIR}&wt=xml")
  SUCCESS=$(echo ${CHECK_SUCCESS} | grep -o '<str name="status">success</str>')
  log "${CHECK_SUCCESS}"
  log "Counter = ${RETRIES}"
  printf "\b${SPINNER:RETRIES++%${#SPINNER}:1}"
done

# Restore the cursor
tput cnorm -- normal

echo -e "\r\033[KSuccessfully restored '${CORE}' core index"
