<?php

// database connection using PDO
$db = new PDO('sqlite:'.__DIR__.'/../data/taxa.db');
$db->exec('PRAGMA synchronous = OFF');
$db->exec('PRAGMA journal_mode = MEMORY');

#$db = new PDO('mysql:host=localhost;dbname=flora','flora','flora');
#$db = new PDO('pgsql:host=localhost;dbname=flora;user=flora;password=flora');

// source darwincore archive
$DWCA="http://ipt.jbrj.gov.br/jbrj/archive.do?r=lista_especies_flora_brasil";

// experimental output
#$COUCHDB="";
#$ELASTICSEARCH="";

