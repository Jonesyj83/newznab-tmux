#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

#########################EDIT THESE#########################
############################################################

#This is the shutdown, true/false
#on, it runs, off and no scripts will be RESTARTED, when all panes are DEAD, killall tmux
#if this is set to false, the script will run 1 loop and terminate
export RUNNING="true"

############################################################

#Set paths
export NEWZPATH="/var/www/newznab"

#Should not need to change
export NEWZNAB_PATH=$NEWZPATH"/misc/update_scripts"
export TESTING_PATH=$NEWZPATH"/misc/testing"
export ADMIN_PATH=$NEWZPATH"/www/admin"

############################################################

#Post Processing Additional is the processing that downloads rar files and attempts to get info for your site
#you are able to set the number of process to be run from 1-9
#trial and error for this, 1 runs always, 2 if more than 200, 3 more than 300 and so on.
#At some point, increasing this begins to slow things down. It will need to be adjusted for your system
#to get the desired performance, 0 will disable all post processing
export POST_TO_RUN="1"

#Enter the session name to be used by tmux
export TMUX_SESSION="Newznab"

#Set, in seconds - how often the monitor.php (left top pane) script should update, 0 may cause errors
export MONITOR_UPDATE="30"

############################################################

#Choose to run update_binaries true/false
export BINARIES="true"

#Choose to run the threaded or non-threaded newznab binaries scripts true/false
#update_binaries.php or update_binaries_threaded.php
export BINARIES_THREADS="true"

#Set, in seconds - how long the update_binaries should sleep between runs, 0 may cause errors
#top right pane
export BINARIES_SLEEP="40"

############################################################

#Choose to run backfill script true/false
export BACKFILL="true"

#Choose to run the threaded or non-threaded newznab backfill scripts true/false
#backfill.php or backfill_threaded.php
export BACKFILL_THREADS="true"

#Set, in seconds - how long the backfill should sleep between runs, 0 may cause errors
#in pane below update_binaries
export BACKFILL_SLEEP="20"

#Set the max amount of unprocessed releases and still allow backfill to run
#set to 0 to disable
export BACKFILL_MAX_RELEASES="0"

#Set the maximum days to backfill, you set the nn+ admin/edit backfill to 1
#this will increment your database by 1 after each backfill loop
#once your backfill numbers reach $MAXDAYS, then it will no long increment the database
#backfill will continue to run, and do no work, at that point you should disable backfill, below
export MAXDAYS="210"

############################################################

#Set the path to the nzb dump you downloaded from torrents, this is the path to bulk files folder of nzbs
#this does not recurse through subfolders
export NZBS="/path/to/nzbs"

#Choose to run import nzb script true/false
export IMPORT="true"

#If you have all of your nzbs in one folder select false
#If, you have all of you nzbs split into separate in with the root at $NZBS then select true
#and 10 nzbs will be imported from each subfolder per loop.
export NZB_THREADS="true"

#How many nzbs to import per loop, if using NZB_THREADS=true the per folder
export NZBCOUNT="20"

#Set, in seconds - how long the nzb-import should sleep between runs, 0 may cause errors
#below backfill
export IMPORT_SLEEP="40"

#Set the max amount of unprocessed releases and still allow nzb-import to run
#set to 0 to disable
export IMPORT_MAX_RELEASES="0"

############################################################

#Create releases, this is really only necessary to tuen off when you only want to postprocess
export RELEASES="true"

#Set, in seconds - how long the update_release should sleep between runs, 0 may cause errors
#bottom right
export RELEASES_SLEEP="40"

#Set the max amount of unprocessed releases and still allow update_releases to run
#set to 0 to disable
export MAX_RELEASES="0"

############################################################

#Choose your database engine, comment the one true/false
#you should have already converted your database to InnoDB engine, if you select true here
export INNODB="true"

############################################################

#Choose to run update_cleanup.php and removespecial.php true/false
#set to false by default, you will need to edit /misc/testing/update_cleanup.php and /misc/testing/removespecial.php
#to actually do anything, directions are in the file
export CLEANUP="false"

#How oftern do you want  update_cleanup.php and removespecial.php to run, in seconds
export CLEANUP_TIMER="3600"

############################################################

#Choose to run update_parsing.php true/false
#set to false by default, you will need to edit /misc/testing/update_parsing.php
#to actually do anything, directions are in the file
export PARSING="false"

#How oftern do you want update_parsing.php to run, in seconds
export PARSING_TIMER="3600"

############################################################

#Choose to run optimise_innodb.php or optimise_mysiam.php script true/false
#set to false by default, you should test the optimse scripts in bin first
export OPTIMISE="false"

#How often to run optimise_myisam on large tables seconds
export MYISAM_LARGE="3600"

#How often to run optimise_innodb on small tables in seconds
export INNODB_SMALL="7200"

#How often to run optimise_innodb on large tables in seconds
export INNODB_LARGE="43200"

############################################################

#How often to update the PreDB in seconds
export PREDB_TIMER="900"

#How often to update the TV Schedule and the In Theaters in seconds
export TVRAGE_TIMER="43200"

############################################################

#Choose to run sphinx.php script true/false
#set to false by default, you should test the script first, php sphinx.php from the bin folder
export SPHINX="true"

#How often to run sphinx in seconds
export SPHINX_TIMER="3600"

############################################################

#Specify your SED binary
export SED="/bin/sed"
#export SED="/usr/local/bin/gsed"

############################################################

#Select some monitoring script, if they are not installed, it will not affect the running of the scripts
#these are set to false by default, enable if you want them
export USE_HTOP="false"
export USE_NMON="false"
export USE_BWMNG="false"
export USE_IOTOP="false"
export USE_MYTOP="false"
export USE_VNSTAT="false"
export USE_IFTOP="false"

############################################################

#Use powerline scripts to display the status bar
export POWERLINE="true"

############################################################

#By using this script you understand that the programmer is not responsible for any loss of data, users, or sanity.
#You also agree that you were smart enough to make a backup of your database and files. Do you agree? yes/no
export AGREED="no"

############################################################

##END OF EDITS##

command -v mysql >/dev/null 2>&1 || { echo >&2 "I require mysql but it's not installed.  Aborting."; exit 1; } && export MYSQL=`command -v mysql`
command -v php5 >/dev/null 2>&1 && export PHP=`command -v php5` || { export PHP=`command -v php`; }
command -v tmux >/dev/null 2>&1 || { echo >&2 "I require tmux but it's not installed.  Aborting."; exit 1; } && export TMUXCMD=`command -v tmux`
command -v nice >/dev/null 2>&1 || { echo >&2 "I require nice but it's not installed.  Aborting."; exit 1; } && export NICE=`command -v nice`


if [[ $USE_HTOP == "true" ]]; then
      command -v htop >/dev/null 2>&1|| { echo >&2 "I require htop but it's not installed.  Aborting."; exit 1; } && export HTOP=`command -v htop`
fi
if [[ $USE_NMON == "true" ]]; then
      command -v nmon >/dev/null 2>&1 || { echo >&2 "I require nmon but it's not installed.  Aborting."; exit 1; } && export NMON=`command -v nmon`
fi
if [[ $USE_BWMNG == "true" ]]; then
      command -v bwm-ng >/dev/null 2>&1|| { echo >&2 "I require bwm-ng but it's not installed.  Aborting."; exit 1; } && export BWMNG=`command -v bwm-ng`
fi
if [[ $USE_IOTOP == "true" ]]; then
      command -v iotop >/dev/null 2>&1|| { echo >&2 "I require iotop but it's not installed.  Aborting."; exit 1; } && export IOTOP=`command -v iotop`
fi
if [[ $USE_MYTOP == "true" ]]; then
      command -v mytop >/dev/null 2>&1|| { echo >&2 "I require mytop but it's not installed.  Aborting."; exit 1; } && export MYTOP=`command -v mytop`
fi
if [[ $USE_VNSTAT == "true" ]]; then
      command -v vnstat >/dev/null 2>&1|| { echo >&2 "I require vnstat but it's not installed. Aborting."; exit 1; } && export VNSTAT=`command -v vnstat`
fi
if [[ $USE_IFTOP == "true" ]]; then
      command -v iftop >/dev/null 2>&1|| { echo >&2 "I require iftop but it's not installed. Aborting."; exit 1; } && export IFTOP=`command -v iftop`
fi

