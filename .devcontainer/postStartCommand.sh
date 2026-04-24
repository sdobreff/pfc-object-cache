#!/bin/bash

chown mysql:mysql /workspaces/mysql -R
sudo crontab /usr/local/crons
service cron start 
sudo rsyslogd
service nginx start
service mysql start
service php8.4-fpm start
mailhog </dev/null &>/dev/null & disown

git config --global --add safe.directory '*'