<?php

$DWCA="http://ipt.jbrj.gov.br/ipt/archive.do?r=lista_especies_flora_brasil";
#$COUCHDB="";

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

function download($url,$output) {
    if(file_Exists($output)) unlink($output);
    system("wget '".$url."' -O '".$output."'");
}

function unzip($file,$dst){
    if(!file_exists($dst)) mkdir($dst);
    $zip = new ZipArchive;
    if ($zip->open($file) === TRUE) {
        $zip->extractTo($dst);
        $zip->close();
    }
}

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

download($DWCA,"data/dwca.zip");

echo "Unzipping...\n";
unzip("data/dwca.zip","data/dwca");
echo "Unzipepd.\n";

$f=fopen("data/dwca/taxon.txt",'r');

$headersRow = fgetcsv($f,0,"\t");
$headers=array();
# read the headers for easier handling
for($i=0;$i<count($headersRow);$i++){
    $headers[$headersRow[$i]] = $i;
}

$insert = $db->prepare("INSERT INTO taxons (taxonID,family,genus,scientificName,scientificNameWithoutAuthorship,scientificNameAuthorship,taxonomicStatus,acceptedNameUsage,higherClassification) VALUES (?,?,?,?,?,?,?,?,?) ;");

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
    $nameWithoutAuthor = trim(str_replace($row[$headers['scientificNameAuthorship']],'',$row[$headers['scientificName']]));

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
        $row[ $headers['higherClassification'] ]
    );
    $insert->execute($taxon);
    echo "Inserted $i = {$taxon[0]}.\n";
    $i++;

}

function http_post($url,$data) {
    $opts = ['http'=>['method'=>'POST','content'=>json_encode($data),'header'=>'Content-type: application/json']];
    $r = file_get_contents($url, NULL, stream_context_create($opts));
    return json_decode($r);
}

if(isset($COUCHDB)) {
    $q = $pdo->select("select * from taxons;");
    $docs = array("docs"=>array());
    while($doc = $q->fetchObject()) $docs["docs"][] = $doc;
    http_post($COUCHDB."/_bulk_docs",$docs);
}

fclose($f);

echo "Done.\n";

