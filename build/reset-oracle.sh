#!/bin/sh

# .git/hooks/post-checkout must by symbolicly linked to this file

# Warning! It appears when an update to this file is checked out,
# the hook runs the previous version. So it takes an extra build
# to run the new reset file

echo "Update hudson directory permissions"
# when hudson updates it resets to 750 which, makes the webroot
# inaccessible. Resetting every build is overkill but does the
# job
chmod 755 /var/lib/hudson

echo "Set Oracle env vars";
export ORACLE_SID=moodle
export ORACLE_HOME=/home/oracle/product/10.2
export LD_LIBRARY_PATH=/home/oracle/product/10.2/lib32

echo "Delete config.php";
rm config.php

echo "Drop tables from database hudson/moodle";
$ORACLE_HOME/bin/sqlplus hudson/moodle <<EOSSPLUS
set verify off
set linesize 150
set pagesize 200
set echo off
set feedback off
set long 32000
set termout off
set showmode off
SPOOL /tmp/DELETEME.SQL
select 'drop table '||table_name||' cascade constraints ;' from user_tables;
SPOOL OFF
@/tmp/DELETEME.SQL
EOSSPLUS

echo "Delete old moodledata";
rm -Rf ../moodledata/

echo "Re-create moodledata";
mkdir ../moodledata
chmod 777 ../moodledata

echo "Reset apache logs";
rm ../moodle_error.log
touch ../moodle_error.log
chmod 777 ../moodle_error.log

echo "Initialize installation";
/usr/bin/php admin/cliupgrade.php \
      --lang=en_utf8 \
      --webaddr="http://brumbies.wgtn.cat-it.co.nz/totara-oracle-hudson" \
      --moodledir="/var/lib/hudson/jobs/Totara-Oracle/workspace" \
      --datadir="/var/lib/hudson/jobs/Totara-Oracle/moodledata" \
      --dbtype="oci8po" \
      --dbname="(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=brumbies.wgtn.cat-it.co.nz)(PORT=1522))(CONNECT_DATA=(SERVICE_NAME = MOODLE)))" \
      --dbhost="" \
      --dbuser="hudson" \
      --dbpass="moodle" \
      --prefix="m_" \
      --verbose=3 \
      --sitefullname="Totara" \
      --siteshortname="Totara" \
      --sitesummary="" \
      --sitenewsitems=0 \
      --adminfirstname=Admin \
      --adminlastname=User \
      --adminemail=simonc@catalyst.net.nz \
      --adminusername=admin \
      --adminpassword="passworD1!" \
      --interactivelevel=0 &> install-log

echo "Generate some test users"
php -f build/generate-users.php

echo "Hit notifications page to complete installation";
wget -O - http://brumbies.wgtn.cat-it.co.nz/totara-oracle-hudson/admin/index.php
