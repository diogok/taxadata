<?php

require 'utils.php';

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

if(isset($_GET['callback'])) {
    echo $_GET['callback'].'(';
}

$db = new PDO('sqlite:data/taxons.db');

$r = new StdClass;
$r->success=true;
$r->result=null;

if($q=='/families') {
    $query = $db->query("select distinct(family) from taxons order by family;");
    $r->result=array();
    while($row = $query->fetchObject()) {
        if(strlen( $row->family ) > 2) {
            $r->result[] = strtoupper($row->family);
        }
    }
} else if($q=="/species") {
    $query = $db->prepare("select * from taxons where LOWER( family ) = ? order by scientificName;");
    $query->execute(array(strtolower($_GET['family'])));

    $r->result=array();
    $miss =array();

    while($row = $query->fetchObject()) {
        $row->links = new StdClass;

        $url = FLORA_LINK;
        foreach($row as $k=>$v) {
            if(is_string($v)) {
                $url = str_replace('{'.$k.'}',$v,$url);
            }
        }
        $row->links->flora = $url;

        $url = OCCS_LINK;
        foreach($row as $k=>$v) {
            if(is_string($v)) {
                $url = str_replace('{'.$k.'}',$v,$url);
            }
        }
        $row->links->occurrences = $url;
        $dwc_url = "http://".$_SERVER["HTTP_HOST"]."/api/v1/geo?scientificName=".urlencode($row->scientificName);
        $row->links->occurrences = str_replace("{url}",urlencode( $dwc_url ),$url);

        if($row->taxonomicStatus == 'accepted') {
            $row->synonyms = array();
            $r->result[] = $row;
        } else {
            $miss[] = $row;
        }
    }

    foreach($miss as $syn) {
        foreach($r->result as $spp) {
            if($spp->scientificName == $syn->acceptedNameUsage) {
                $spp->synonyms[] = $syn;
            }
        }
    }
} else if($q=='/geo') {
    $json = http_get(DWC_SERVICES."/search/tapir?url=".urlencode(TAPIR)."&field=scientificName&value=".urlencode($_GET["scientificName"])."");
    foreach($json->records as $r) {
        if(isset($r->decimalLatitude)) $r->decimalLatitude = (float) $r->decimalLatitude;
        if(isset($r->decimalLongitude)) $r->decimalLongitude = (float) $r->decimalLongitude;
    }
    /* */
    $r = $json->records;
    /* */
    /*
    $geojson = http_post(DWC_SERVICES."/convert?from=json&to=geojson",$json->records);
    foreach($geojson->features as $i=>$feature) {
        $feature->geometry->coordinates[0] = (float) $feature->geometry->coordinates[0];
        $feature->geometry->coordinates[1] = (float) $feature->geometry->coordinates[1];
    }
    $r = $geojson;
    */
}

echo json_encode($r);

if(isset( $_GET['callback'] )) {
    echo ');';
}
