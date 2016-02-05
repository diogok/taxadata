#!/bin/bash

supervisord &

mkdir data -p

sleep 3

while :
do
  php app/dwca2sql.php
  sleep 86400
done

