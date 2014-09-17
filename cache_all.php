<?php

header('Content-Type: application/json');
echo '[[]';
$url = "http://".$_SERVER[ 'HTTP_HOST' ]."/".str_replace($_SERVER['REQUEST_URI'],"cache_all.php","")."api/v1/occurrences?scientificName=";
$db = new PDO('sqlite:../data/taxons.db');
$query = $db->query("select scientificNameWithoutAuthorship from taxons where taxonomicStatus='accepted';");
while($name = $query->fetchColumn(0)) {
    $r = file_get_contents($url.urlencode( $name ));
    echo ",".$r;
}
echo ']';

