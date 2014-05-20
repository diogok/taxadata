#!/usr/bin/env bash

apt-get update
apt-get upgrade -y
apt-get autoremove -y

# add apache and php
if [[ ! -e ~/.apache_done ]]; then
    # install apache and php
    apt-get install apache2 libapache2-mod-php5 php5-pgsql php5 php5-cli php5-curl php5-common php5-gd php5-xdebug php5-sqlite php5-pgsql php5-mysql -y
    a2enmod rewrite
    service apache2 stop
    # use the project folder as main folder
    rm /var/www -Rf
    ln -s /vagrant /var/www
    chown vagrant /var/lock/apache2 -Rf
    # setup apache vars
    sed -i -e 's/RUN_USER=www-data/RUN_USER=vagrant/g' /etc/apache2/envvars
    cp /vagrant/default.conf /etc/apache2/sites-available/000-default.conf
    # setup some php env vars
    sed -i -e 's/memory_limit.*/memory_limit=512M/g' /etc/php5/apache2/php.ini
    sed -i -e 's/upload_max_filesize.*/upload_max_filesize=128M/g' /etc/php5/apache2/php.ini
    sed -i -e 's/post_max_size.*/post_max_size=128M/g' /etc/php5/apache2/php.ini
    sed -i -e 's/display_errors.*/display_erros=On/g' /etc/php5/apache2/php.ini
    # restart
    service apache2 start
    touch ~/.apache_done
fi

if [[ ! -e /vagrant/data ]]; then
    su vagrant -lc 'cd /vagrant && php dwca2sql.php';
fi

# done
echo "Done bootstraping"

