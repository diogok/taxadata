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
    'VARIEDADE'=>"variety",
    'É sinônimo HETEROTIPICO'=>'synonym_of',
    'É sinônimo HOMOTIPICO'=>'synonym_of',
    'É sinônimo BASIONIMO'=>'synonym_of',
    'Tem como sinônimo HETEROTIPICO'=>'has_synonym',
    'Tem como sinônimo HOMOTIPICO'=>'has_synonym',
    'Tem como sinônimo BASIONIMO'=>'has_synonym'
);

$data = __DIR__.'/../data';

# create data dir if not exists
if(!file_exists($data)) mkdir($data);

# creates db if not exists
if(!file_exists($data."/taxa.db")) $create=true;
else $create=false;

// create table if not exists
$db->exec(file_get_contents("schema.sql"));
$err = $db->errorInfo();
if($err[0] != "00000") var_dump($db->errorInfo());

// clean table
$db->exec("DELETE FROM taxa ;");
$err = $db->errorInfo();
if($err[0] != "00000") var_dump($db->errorInfo());

// download
echo "Downloading...\n";
#if(file_Exists($data.'/dwca.zip')) unlink($data.'/dwca.zip');
#system("wget '".$DWCA."' -O '".$data."/dwca.zip'");
echo "Downloaded.\n";

// Unzing
echo "Unzipping...\n";
$dst=$data."/dwca";
if(!file_exists($dst)) mkdir($dst);
$zip = new ZipArchive;
if ($zip->open($data."/dwca.zip") === TRUE) {
    $zip->extractTo($dst);
    $zip->close();
}
echo "Unzipped.\n";

$source = "";

// Try to get title and version
$eml = file_get_contents($dst."/eml.xml");
preg_match('@<title[^>]*>([^<]+)</title>@',$eml,$reg);
if(isset($reg[1])) {
  $source .= " ".$reg[1];
}
preg_match('@packageId="[^/]+/v([^"]+)"@',$eml,$reg);
if(isset($reg[1])) {
  $source .= " v".$reg[1];
}
$source = trim($source);

// start reading the taxa
$f=fopen($dst."/taxon.txt",'r');

// read the headers for easier handling
$headersRow = fgetcsv($f,0,"\t");
$headers=array();
for($i=0;$i<count($headersRow);$i++){
    $headers[$headersRow[$i]] = $i;
}

$insert = $db->prepare("INSERT INTO taxa (`taxonID`,`family`,`genus`,`scientificName`,`scientificNameWithoutAuthorship`,`scientificNameAuthorship`,`taxonomicStatus`,`acceptedNameUsage`,`taxonRank`,`higherClassification`,`source`) VALUES (?,?,?,?,?,?,?,?,?,?,?) ;");
$err = $db->errorInfo();
if($err[0] != "00000") var_dump($db->errorInfo());

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

    $t = [];
    foreach($headers as $k=>$v) {
        $t[$k]=$row[$v];
    }

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
        $row[ $headers['higherClassification'] ],
        $source
    );
    $insert->execute($taxon);
    #echo "Inserted $i = {$taxon[0]}.\n";
    $err = $insert->errorInfo();
    if($err[0] != "00000") var_dump($insert->errorInfo());
    $i++;
}
fclose($f);

echo "Inserted.\n";

// start reading the relations
$f=fopen($dst."/resourcerelationship.txt",'r');

// read the headers for easier handling
$headersRow = fgetcsv($f,0,"\t");
$headers=array();
for($i=0;$i<count($headersRow);$i++){
    $headers[$headersRow[$i]] = $i;
}

$update = $db->prepare("UPDATE taxa SET acceptedNameUsage=(SELECT acceptedNameUsage FROM taxa where taxonID=?) where taxonID=?");
$err = $db->errorInfo();
if($err[0] != "00000") var_dump($db->errorInfo());

$i=0;
echo "Updating...\n";
while($row = fgetcsv($f,0,"\t")) {
    $relation = ( $strings[$row[$headers['relationshipOfResource']]]);

    $data=false;
    if($relation == 'synonym_of') {
      $data = [$row[1],$row[0]];
    } else if($relation == 'has_synonym') {
      $data = [$row[0],$row[1]];
    }

    if($data) {
      $update->execute($data);
      $err = $update->errorInfo();
      if($err[0] != "00000") var_dump($update->errorInfo());
    }
    $i++;
}

fclose($f);

# experimental output to couchdb
if(isset($COUCHDB)) {
    $q = $pdo->select("select * from taxa;");
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
    $q = $pdo->select("select * from taxa;");
    while($doc = $q->fetchObject()) {
        $doc->metadata = array("type"=>"taxon","created"=>time(),"source"=>$DWCA);
        $opts = ['http'=>['method'=>'POST','content'=>json_encode($doc),'header'=>'Content-type: application/json']];
        file_get_contents($ELASTICSEARCH."/taxon", NULL, stream_context_create($opts));
    }
}


echo "Done.\n";

