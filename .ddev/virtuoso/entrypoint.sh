#!/bin/bash

#
# Disclaimer: Forked from original entry point file.
#

#
#  Set mask for file permissions
#
umask 0027

#
#  Default programs
#
export VIRTUOSO="$VIRTUOSO_HOME/bin/virtuoso-t"
export INIFILE="$VIRTUOSO_HOME/bin/inifile"

#
#  Allow user to set environment variables to overrule values in the default virtuoso.ini
#
#  Environment variables should be named like:
#
#  VIRT_SECTION_KEY=VALUE
#
#  where
#
#  VIRT is common prefix to group such variables together
#  SECTION is the name of the [section] in virtuoso.ini
#       KEY is the name of a key within the section
#  VALUE is the text to be written into the key
#
#  The variable names can be placed in either uppercase (most commonly used) or mixed case, without having to exactly match the case inside
#  the virtuoso.ini file:
#
#  VIRT_Parameters_NumberOfBuffers is same as VIRT_PARAMETERS_NUMBEROFBUFFERS
#
#  Examples:
#       VIRT_PARAMETERS_NUMBEROFBUFFERS=1000000
#  VIRT_HTTPSERVER_PORT=80
#
virtuoso_ini_from_env()
{
  printenv | grep -i ^VIRT_ | while read -r a
  do
    setting=$(echo "$a" | cut -d'=' -f 1)
    value=$(echo "$a" | cut -d'=' -f 2)
    section=$(echo "$setting" | cut -d'_' -f 2)
    key=$(echo "$setting" | cut -d'_' -f 3-)

    "$INIFILE" +inifile virtuoso.ini +section "$section" +key "$key" +value "$value"

    # Don't need any plugin
    "$INIFILE" -f virtuoso.ini -s Plugins -k - -v -
  done
}

#
#  Check to see if this instance has already been initialized
#
initialize_virtuoso_directory()
{
  if [ ! -f virtuoso.ini ]
  then
    #
    #  Check for custom virtuoso.ini
    #
    if [ -f "$VIRTUOSO_INI_FILE" ]
    then
      cp "$VIRTUOSO_INI_FILE" /database/virtuoso.ini
    else
      cp "$VIRTUOSO_HOME"/installer/virtuoso.ini.sample /database/virtuoso.ini
    fi

    #
    #  Convert environment variables to virtuoso.ini settings
    #
    echo "Applying settings to virtuoso.ini"
    virtuoso_ini_from_env

    #
    #  Create an initial database
    #
    echo "Creating initial database"
    $VIRTUOSO -f +checkpoint-only

    #
    #  Process any initdb.d scripts
    #
    echo "Running initialization scripts"
    for file in "$VIRTUOSO_HOME"/initdb.d/*
    do
      scriptname=$(basename "$file")
      case "$scriptname" in
        *.sh)
          echo ""
          echo "* Running SHELL script: [$scriptname]"
          /bin/sh "$file"
          if test $? -ne 0
          then
            echo "** SHELL SCRIPT FAILED **"
          fi
          ;;

        *.sql)
          echo ""
          echo "* Running SQL script: [$file]"
          cp "$file" autoexec.isql
          "$VIRTUOSO" -f +checkpoint-only
          if test $? -ne 0
          then
            echo "** SQL SCRIPT FAILED **"
          fi
          rm -f autoexec.isql
          ;;

          *)
          [ -f "$file" ] && echo "NOTE: Skipping file: [$scriptname]"
          ;;
      esac
    done

    #
    #  Set the initial password
    #
    echo "Setting passwords"
    "$VIRTUOSO" -f +checkpoint-only +pwdold dba +pwddba "$DBA_PASSWORD" +pwddav "$DAV_PASSWORD"
  fi
}

initialize_virtuoso_directory

echo "Starting the Virtuoso Server"

# Not running in foreground as we want to be able to shutdown Virtuoso without
# shutting down the container.
$VIRTUOSO +wait

# Keep the container alive.
tail -f /dev/null

#
#  Try to execute the given command
#
exec "$CMD" "$@"
exit 1
