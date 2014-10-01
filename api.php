<?php

// config including DB
require 'config.php';


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
#$db = new PDO('sqlite:data/taxons.db');

// Default response
$r = new StdClass;
$r->success=true;
$r->result=null;

if($q=='/search/species') { // specie search
    $q = $db->prepare("select * from taxons where scientificName like ? "
                     ."and taxonomicStatus='accepted' order by family, scientificName limit 100;");
    $q->execute(array( "%".$_GET["query"]."%" ));
    $r->result=array();
    while($row = $q->fetchObject()) {
        $r->result[] = $row;
    }
} else if($q=='/families') { // families listing
    $query = $db->query("select distinct(family) from taxons order by family;");
    $r->result=array();
    while($row = $query->fetchObject()) {
        if(strlen( $row->family ) > 2) {
            $r->result[] = strtoupper($row->family);
        }
    }
} else if($q=="/species") { // species of family
    $query = $db->prepare("select * from taxons where LOWER( family ) = ? order by scientificName;");
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
    $q = $db->prepare("select * from taxons where scientificName = ? or acceptedNameUsage like ? or scientificNameWithoutAuthorship = ?");
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
        $r->result = $db->query("select * from taxons where scientificName = '".$miss[0]->acceptedNameUsage."';")->fetchObject();
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
} else if($q=='/occurrences') { // occurrences of specie
    $records = array();
    $got=array();

    $q=$db->prepare("select * from taxons where scientificName=? OR scientificNameWithoutAuthorship=?");
    $q->execute(array($_GET["scientificName"],$_GET["scientificName"]));
    $taxon = $q->fetchObject();
    if($taxon) {
        // cache the search for a week
        $file = "data/cache/".$taxon->family."/".$taxon->scientificNameWithoutAuthorship.".json";
        if(!file_exists("data/cache")) {
            mkdir("data/cache");
        }
        if(!file_exists("data/cache/".$taxon->family)) {
            mkdir("data/cache/".$taxon->family);
        }
        if(file_exists($file)) {
            $mtime = filemtime($file);
            if($mtime < (time() - (7*24*60*60))) {
                unlink($file);
            }
        }
        if(file_exists($file)) {
            // if cache recent
            $r = json_decode(file_get_contents($file));
        } else {
            // real query
            $q=$db->prepare("select * from taxons where acceptedNameUsage=?");
            $q->execute(array($taxon->scientificName));
            $taxons=array();
            while($t=$q->fetchObject()) $taxons[]=$t;

            foreach($taxons as $taxon) { // accepted and synonyms
                foreach($TAPIR as $tapir) {
                    $parts=explode("|",$tapir);
                    $url  = $parts[0];
                    $field1 = $parts[1];
                    $field2 = $parts[2];
                    $value  = $taxon->$field2;

                    // search using the dwc_services
                    $json = http_get($DWC_SERVICES."/search/tapir?url=".urlencode($url)
                                                  ."&field=".$field1."&value=".urlencode($value));
                    // fixes using the dwc_services
                    $fixed = http_post($DWC_SERVICES."/fix",$json->records);
                    foreach($fixed as $r) {
                        $_s0 = trim( $taxon->scientificNameWithoutAuthorship );
                        $_c  = strlen($_s0);
                        $_s1 = trim( substr($r->scientificName,0,$_c) );
                        if($_s0 == $_s1) {
                            // and populate the taxon data of the occurrence with our updated taxa
                            if(!isset($got[$r->occurrenceID])) {
                                foreach($taxon as $k=>$v) {
                                    $r->$k=$v;
                                }
                                $records[] = $r;
                                $got[$r->occurrenceID] = true;
                            }
                        }
                    }
                }
                // also search gbif
                $json = http_get($DWC_SERVICES."/search/gbif?field=scientificName&value=".urlencode($taxon->scientificNameWithoutAuthorship));
                // also fix gbif data
                $fixed = http_post($DWC_SERVICES."/fix",$json->results);
                foreach($fixed as $r) {
                    // and fix taxonomy
                    foreach($taxon as $k=>$v) $r->$k=$v;
                    if(!isset($got[$r->occurrenceID])) {
                        $records[] = $r;
                        $got[$r->occurrenceID] = true;
                    }
                }
            }

            $r = $records;

            if(!file_exists($file)) { // save cache
                file_put_contents($file,json_encode($r));
            }
        }
    }

}

echo json_encode($r);

// JSONP support
if(isset( $_GET['callback'] )) {
    echo ');';
}

