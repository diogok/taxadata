<?php

// config including DB
require '../app/config.php';
#$db = new PDO('sqlite:'__DIR__.'/../data/taxa.db');

// utils
function http_get($url) {
    return json_decode(file_get_contents($url));
}

function http_post($url,$data) {
    $opts = ['http'=>['method'=>'POST','content'=>json_encode($data),'header'=>'Content-type: application/json']];
    $r = file_get_contents($url, NULL, stream_context_create($opts));
    return json_decode($r);
}

// CORS support
if(isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Headers: X-Requested-With');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

$q=$_GET['q'];

header('Content-Type: application/json');

// JSONP support
if(isset($_GET['callback'])) {
    echo $_GET['callback'].'(';
}

// now comes from config
#$db = new PDO('sqlite:data/taxa.db');

// Default response
$r = new StdClass;
$r->success=true;
$r->result=null;

if($q=='/search/species') { // specie search
    $q = $db->prepare("select * from taxa where scientificName like ? "
                     ."and taxonomicStatus='accepted' order by family, scientificName limit 100;");
    $q->execute(array( "%".$_GET["query"]."%" ));
    $r->result=array();
    while($row = $q->fetchObject()) {
        $r->result[] = $row;
    }
} else if($q=='/families') { // families listing
    $query = $db->query("select distinct(family) from taxa order by family;");
    $r->result=array();
    while($row = $query->fetchObject()) {
        if(strlen( $row->family ) > 2) {
            $r->result[] = strtoupper($row->family);
        }
    }
} else if($q=="/species") { // species of family
    $query = $db->prepare("select * from taxa where LOWER( family ) = ? order by scientificName;");
    $query->execute(array(strtolower($_GET['family'])));

    $r->result=array();
    $miss =array();

    while($row = $query->fetchObject()) {
        if($row->taxonomicStatus == 'accepted') {
            $row->synonyms = array();
            $r->result[] = $row;
        } else { // save the synonyms for later
            $miss[] = $row;
        }
    }

    foreach($miss as $syn) {
        foreach($r->result as $spp) { 
            // put the synonyms in
            if($spp->scientificName == $syn->acceptedNameUsage) {
                $spp->synonyms[] = $syn;
            }
        }
    }
} else if($q=='/specie') { // specie data, fixing synonyms and stuff
    $q = $db->prepare("select * from taxa where scientificName = ? or acceptedNameUsage like ? or scientificNameWithoutAuthorship = ?");
    $q->execute(array($_GET["scientificName"],"%".$_GET["scientificName"]."%",$_GET["scientificName"]));

    $r->result=null;
    $miss=[];

    while($row = $q->fetchObject()) {
        if($row->taxonomicStatus == 'accepted' &&
            ($row->scientificName == $_GET["scientificName"] || $row->scientificNameWithoutAuthorship == $_GET["scientificName"] )
        ) {
            $r->result = $row;
            $r->result->synonyms=[];
        } else {
            $miss[] = $row; // save the synonyms for later
        }
    }

    // case this is a synonym
    if($r->result == null && count( $miss ) >= 1) {
        $r->result = $db->query("select * from taxa where scientificName = '".$miss[0]->acceptedNameUsage."';")->fetchObject();
    }

    if($r->result != null) {
        foreach($miss as $syn) {
            // get the saved synonyms
            if($syn->acceptedNameUsage == $r->result->scientificName) {
                $r->result->synonyms[] = $syn;
            }
        }
    } else if(count($miss) >= 1) {
        $r->result = $miss[0];
    } 

}

echo json_encode($r);

// JSONP support
if(isset( $_GET['callback'] )) {
    echo ');';
}

