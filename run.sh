#!/bin/bash

/usr/sbin/apache2 -DFOREGROUND & 

sleep 3

while :
do
  php app/dwca2sql.php
  sleep 86400
done
