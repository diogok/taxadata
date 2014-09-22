<?php

include 'config.php';

// translation strings
$strings = array(
    null=>'',
    ''=>'',
    ' '=>' ',
    'NOME_ACEITO'=>'accepted',
    'SINONIMO'=>"synonym",
    'CLASSE'=>"class",
    'DIVISAO'=>"division",
    'ESPECIE'=>"species",
    'FAMILIA'=>"family",
    'FORMA'=>"form",
    'GENERO'=>"genus",
    'ORDEM'=>"order",
    'SUB_ESPECIE'=>"subspecies",
    'SUB_FAMILIA'=>"subfamily",
    'TRIBO'=>"tribe",
    'VARIEDADE'=>"variety"
);

# create data dir if not exists
if(!file_exists("data")) mkdir("data");

# creates db if not exists
if(!file_exists("data/taxons.db")) $create=true;
else $create=false;

// comes from config
#$db = new PDO('sqlite:data/taxons.db');

// create table if not exists
$db->exec(file_get_contents("schema.sql"));
// clean table
$db->exec("DELETE FROM taxons ;");

// download
echo "Downloading...\n";
if(file_Exists('data/dwca.zip')) unlink('data/dwca.zip');
system("wget '".$DWCA."' -O 'data/dwca.zip'");
echo "Downloaded.\n";

// Unzing
echo "Unzipping...\n";
$dst="data/dwca";
if(!file_exists($dst)) mkdir($dst);
$zip = new ZipArchive;
if ($zip->open("data/dwca.zip") === TRUE) {
    $zip->extractTo($dst);
    $zip->close();
}
echo "Unzipped.\n";

// start reading the taxons
$f=fopen("data/dwca/taxon.txt",'r');

// read the headers for easier handling
$headersRow = fgetcsv($f,0,"\t");
$headers=array();
for($i=0;$i<count($headersRow);$i++){
    $headers[$headersRow[$i]] = $i;
}

$insert = $db->prepare("INSERT INTO taxons (taxonID,family,genus,scientificName,scientificNameWithoutAuthorship,scientificNameAuthorship,taxonomicStatus,acceptedNameUsage,taxonRank,higherClassification) VALUES (?,?,?,?,?,?,?,?,?,?) ;");

$i=0;
echo "Inserting...\n";
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
    $nameWithoutAuthor = trim(str_replace(" ".$row[$headers['scientificNameAuthorship']],'',$row[$headers['scientificName']]));

    # insert
    $taxon = array(
        $row[ $headers['taxonID'] ],
        $row[ $headers['family'] ],
        $row[ $headers['genus'] ],
        $row[ $headers['scientificName'] ],
        $nameWithoutAuthor,
        $row[ $headers['scientificNameAuthorship'] ],
        $row[ $headers['taxonomicStatus'] ],
        $row[ $headers['acceptedNameUsage'] ],
        $row[ $headers['taxonRank'] ],
        $row[ $headers['higherClassification'] ]
    );
    $insert->execute($taxon);
    echo "Inserted $i = {$taxon[0]}.\n";
    $i++;

}

# experimental output to couchdb
if(isset($COUCHDB)) {
    $q = $pdo->select("select * from taxons;");
    $docs = array("docs"=>array());
    while($doc = $q->fetchObject()) {
        $doc->metadata = array("type"=>"taxon","created"=>time(),"source"=>$DWCA);
        $docs["docs"][] = $doc; 
    }
    $opts = ['http'=>['method'=>'POST','content'=>json_encode($docs),'header'=>'Content-type: application/json']];
    file_get_contents($COUCHDB."/_bulk_docs", NULL, stream_context_create($opts));
}

# experimental output to elasticsearch
if(isset($ELASTICSEARCH)) {
    $q = $pdo->select("select * from taxons;");
    while($doc = $q->fetchObject()) {
        $doc->metadata = array("type"=>"taxon","created"=>time(),"source"=>$DWCA);
        $opts = ['http'=>['method'=>'POST','content'=>json_encode($doc),'header'=>'Content-type: application/json']];
        file_get_contents($ELASTICSEARCH."/taxon", NULL, stream_context_create($opts));
    }
}

fclose($f);

echo "Done.\n";

