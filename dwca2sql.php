<?php

require 'utils.php';
require 'strings.php';

# create data dir if not exists
if(!file_exists("data")) mkdir("data");

# creates db if not exists
if(!file_exists("data/taxons.db")) $create=true;
else $create=false;

$db = new PDO('sqlite:data/taxons.db');

if($create) {
    $db->exec(file_get_contents("schema.sql"));
} else {
    $db->exec("DELETE FROM taxons ;");
}

download(DWCA,"data/dwca.zip");
unzip("data/dwca.zip","data/dwca");

$f=fopen("data/dwca/taxon.txt",'r');

$headersRow = fgetcsv($f,0,"\t");
$headers=array();
# read the headers for easier handling
for($i=0;$i<count($headersRow);$i++){
    $headers[$headersRow[$i]] = $i;
}

$insert = $db->prepare("INSERT INTO taxons (taxonID,family,scientificName,scientificNameWithoutAuthorship,scientificNameAuthorship,taxonomicStatus,acceptedNameUsage,higherClassification) VALUES (?,?,?,?,?,?,?,?) ;");

while($row = fgetcsv($f,0,"\t")) {
    # translate taxonomicStatus
    $row[$headers['taxonomicStatus']] = $strings[$row[$headers['taxonomicStatus']]] ;

    # translate taxonRank 
    $row[$headers['taxonRank']] = $strings[$row[$headers['taxonRank']]] ;

    # only interested in species, subspecies and variety
    $rank = $row[$headers['taxonRank']];
    if($rank != 'species' && $rank != 'subspecies' && $rank != 'variety') {
        continue;
    }

    # an accepted taxa should have its own name as accepted name
    if($row[$headers['taxonomicStatus']] == 'accepted') {
        $row[$headers['acceptedNameUsage']] = $row[$headers['scientificName']];
    }

    #scientificName without author
    $nameWithoutAuthor = trim(str_replace($row[$headers['scientificNameAuthorship']],'',$row[$headers['scientificName']]));

    # insert
    $taxon = array(
        $row[ $headers['taxonID'] ],
        $row[ $headers['family'] ],
        $row[ $headers['scientificName'] ],
        $nameWithoutAuthor,
        $row[ $headers['scientificNameAuthorship'] ],
        $row[ $headers['taxonomicStatus'] ],
        $row[ $headers['acceptedNameUsage'] ],
        $row[ $headers['higherClassification'] ]
    );
    $insert->execute($taxon);
}

fclose($f);

