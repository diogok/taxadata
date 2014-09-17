<?php

// database connection using PDO
$db = new PDO('sqlite:data/taxons.db');
#$db = new PDO('mysql:host=localhost;dbname=flora','flora','flora');
#$db = new PDO('pgsql:host=localhost;dbname=flora;user=flora;password=flora');

// source darwincore archive
$DWCA="http://ipt.jbrj.gov.br/ipt/archive.do?r=lista_especies_flora_brasil";

// experimental output
#$COUCHDB="";
#$ELASTICSEARCH="";


// to handle occurrences
$DWC_SERVICES="http://cncflora.jbrj.gov.br/dwc/api/v1";
#$DWC_SERVICES="http://192.168.50.30:3000/api/v1";
#$DWC_SERVICES="http://localhost:3000/api/v1";

// to search occurrences
$TAPIR=array();
$TAPIR[]="http://tapirlink.jbrj.gov.br/tapir.php/tapir.php/RB|Genus|genus";
$TAPIR[]="http://tapir.cria.org.br/tapirlink/tapir.php/specieslink|scientificName|scientificNameWithoutAuthorship";

