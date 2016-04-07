#!/bin/bash

composer install

nginx
php-fpm7.0

mkdir data -p

sleep 3

while :
do
  php app/dwca2sql.php
  sleep 86400
done

