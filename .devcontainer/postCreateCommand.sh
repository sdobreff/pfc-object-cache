#!/bin/bash

CODESPACE_WORKSPACE_FOLDER="/workspaces"

sudo service mysql start
sudo service mysql stop
sudo mv /var/lib/mysql/ $CODESPACE_WORKSPACE_FOLDER/mysql/
sudo chown mysql:mysql /workspaces/mysql -R
sudo cp $CODESPACE_WORKSPACE_FOLDER/$RepositoryName/.gitpod/mysqld.cnf /etc/mysql/mysql.conf.d/mysql.cnf
# sudo mkdir /var/lib/mysql
sudo mkdir /var/log/mysql
sudo chown mysql:mysql /var/log/mysql -R
sudo chown mysql:mysql $CODESPACE_WORKSPACE_FOLDER/mysql -R
#sudo usermod -d /var/lib/mysql/ mysql

sudo service mysql start

sudo rsyslogd
sudo service php8.4-fpm start
sudo service nginx start
# sudo mailhog </dev/null &>/dev/null & disown

sudo ln -s $CODESPACE_WORKSPACE_FOLDER/$RepositoryName /var/www/html/wp-content/plugins
sudo chown www-data:www-data /var/www/html/wp-content/plugins/$RepositoryName

sudo ln -s $CODESPACE_WORKSPACE_FOLDER/$RepositoryName /var/www/html-multi/wp-content/plugins
sudo chown www-data:www-data /var/www/html-multi/wp-content/plugins/$RepositoryName

sudo ln -s $CODESPACE_WORKSPACE_FOLDER/$RepositoryName/.gitpod-vscode /var/www/html/.vscode
sudo chown www-data:www-data /var/www/html/.vscode

sudo ln -s $CODESPACE_WORKSPACE_FOLDER/$RepositoryName/.gitpod-vscode /var/www/html-multi/.vscode
sudo chown www-data:www-data /var/www/html-multi/.vscode

sudo mv /var/www/html/wp-content/plugins/ $CODESPACE_WORKSPACE_FOLDER/
sudo rm -rf /var/www/html/wp-content/plugins/
sudo rm -rf $CODESPACE_WORKSPACE_FOLDER/plugins/plugins/
sudo mv /var/www/html-multi/wp-content/plugins/ $CODESPACE_WORKSPACE_FOLDER/plugins-multi/
sudo rm -rf /var/www/html-multi/wp-content/plugins/
sudo rm -rf $CODESPACE_WORKSPACE_FOLDER/plugins-multi/plugins/
sudo mv /var/www/html/wp-content/uploads/ $CODESPACE_WORKSPACE_FOLDER/
sudo rm -rf /var/www/html/wp-content/uploads/
sudo rm -rf $CODESPACE_WORKSPACE_FOLDER/uploads/uploads/
sudo mv /var/www/html-multi/wp-content/uploads/ $CODESPACE_WORKSPACE_FOLDER/uploads-multi/
sudo rm -rf /var/www/html-multi/wp-content/uploads/
sudo rm -rf $CODESPACE_WORKSPACE_FOLDER/uploads-multi/uploads/
sudo ln -s $CODESPACE_WORKSPACE_FOLDER/plugins /var/www/html/wp-content
sudo ln -s $CODESPACE_WORKSPACE_FOLDER/plugins-multi /var/www/html-multi/wp-content/plugins
sudo ln -s $CODESPACE_WORKSPACE_FOLDER/uploads /var/www/html-multi/wp-content
sudo ln -s $CODESPACE_WORKSPACE_FOLDER/uploads-multi /var/www/html-multi/wp-content/uploads
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/plugins/ -R
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/plugins-multi/ -R
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/uploads/ -R
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/uploads-multi/ -R
sudo chown www-data:www-data /var/www/html/wp-content -R
sudo chown www-data:www-data /var/www/html-multi/wp-content -R

cp .pre-commit $CODESPACE_WORKSPACE_FOLDER/$RepositoryName/.git/hooks/pre-commit
chmod +x $CODESPACE_WORKSPACE_FOLDER/$RepositoryName/.git/hooks/pre-commit

FLAG="$CODESPACE_WORKSPACE_FOLDER/$RepositoryName/bin/install-dependencies.sh"

# search the flag file
if [ -f $FLAG ]; then
 /bin/bash $FLAG
fi

FLAG="$CODESPACE_WORKSPACE_FOLDER/$RepositoryName/bin/set-assets.sh"

# search the flag file
if [ -f $FLAG ]; then
 /bin/bash $FLAG
fi

# sudo adduser gitpod www-data
sudo chown www-data:www-data CODESPACE_WORKSPACE_FOLDERwww -R
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/plugins -R
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/plugins-multi -R
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/uploads-multi -R
sudo chown www-data:www-data $CODESPACE_WORKSPACE_FOLDER/uploads-multi -R
#sudo chmod g+rw /var/www -R

sudo mysql -u root wordpress-multi -e "update gitpod_site set domain='$CODESPACE_NAME-81.$GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN' where id=1";
sudo mysql -u root wordpress-multi -e "update gitpod_blogs set domain='$CODESPACE_NAME-81.$GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN' where blog_id=1";
sudo mysql -u root wordpress-multi -e "update gitpod_options set option_value='https://$CODESPACE_NAME-81.$GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN/' where option_name='siteurl'";
sudo mysql -u root wordpress-multi -e "update gitpod_options set option_value='https://$CODESPACE_NAME-81.$GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN/' where option_name='home'";

sudo crontab /usr/local/crons
service cron start

service mysql start
sudo mailhog </dev/null &>/dev/null & disown