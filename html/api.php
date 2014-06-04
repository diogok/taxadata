<?php

require 'config.php';

$time=time();
$ltime=$time;
function loge($str) {
    global $time;
    global $ltime;
    $mytime = time();
    echo "-".$str.":".($mytime-$ltime)."\n";
    #echo "-".$str."\n";
    #echo "--from last:".($mytime-$ltime)."\n";
    #echo "--from start:".($mytime-$time)."\n";
    $ltime=time();
    flush();
}

function http_get($url) {
    return json_decode(file_get_contents($url));
}

function http_post($url,$data) {
    $opts = ['http'=>['method'=>'POST','content'=>json_encode($data),'header'=>'Content-type: application/json']];
    $r = file_get_contents($url, NULL, stream_context_create($opts));
    return json_decode($r);
}

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

$db = new PDO('sqlite:../data/taxons.db');

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

        $url = $FLORA_LINK;
        foreach($row as $k=>$v) {
            if(is_string($v)) {
                $url = str_replace('{'.$k.'}',$v,$url);
            }
        }
        $row->links->flora = $url;

        $url = $OCCS_LINK;
        foreach($row as $k=>$v) {
            if(is_string($v)) {
                $url = str_replace('{'.$k.'}',$v,$url);
            }
        }
        $row->links->occurrences = $url;
        $dwc_url = "http://".$_SERVER["HTTP_HOST"]."/api/v1/occurrences?scientificName=".urlencode($row->scientificNameWithoutAuthorship);
        $row->links->occurrences = str_replace("{url}",urlencode($dwc_url),$url);

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
} else if($q=='/occurrences') {
    $records = array();
    $got=array();

    $q=$db->prepare("select * from taxons where scientificName=? OR scientificNameWithoutAuthorship=?");
    $q->execute(array($_GET["scientificName"],$_GET["scientificName"]));
    $taxon = $q->fetchObject();

    $file = "../data/cache/".$taxon->family."/".$taxon->scientificNameWithoutAuthorship.".json";
    if(!file_exists("../data/cache")) {
        mkdir("../data/cache");
    }
    if(!file_exists("../data/cache/".$taxon->family)) {
        mkdir("../data/cache/".$taxon->family);
    }
    $mtime = filemtime($file);
    if(file_exists($file)) {
        if($mtime < (time() - (7*24*60*60))) {
            unlink($file);
        }
    }
    if(file_exists($file)) {
        $r = json_decode(file_get_contents($file));
    } else {
        $q=$db->prepare("select * from taxons where acceptedNameUsage=?");
        $q->execute(array($taxon->scientificName));
        $taxons=array();
        while($t=$q->fetchObject()) $taxons[]=$t;

        foreach($taxons as $taxon) {
            foreach($TAPIR as $tapir) {
                $parts=explode("|",$tapir);
                $url  = $parts[0];
                $field1 = $parts[1];
                $field2 = $parts[2];
                $value  = $taxon->$field2;

                $json = http_get($DWC_SERVICES."/search/tapir?url=".urlencode($url)
                                              ."&field=".$field1."&value=".urlencode($value));
                $fixed = http_post($DWC_SERVICES."/fix",$json->records);
                foreach($fixed as $r) {
                    $_s0 = trim( $taxon->scientificNameWithoutAuthorship );
                    $_c  = strlen($_s0);
                    $_s1 = trim( substr($r->scientificName,0,$_c) );
                    if($_s0 == $_s1) {
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
            $json = http_get($DWC_SERVICES."/search/gbif?field=scientificName&value=".urlencode($taxon->scientificNameWithoutAuthorship));
            $fixed = http_post($DWC_SERVICES."/fix",$json->results);
            foreach($fixed as $r) {
                foreach($taxon as $k=>$v) $r->$k=$v;
                if(!isset($got[$r->occurrenceID])) {
                    $records[] = $r;
                    $got[$r->occurrenceID] = true;
                }
            }
        }

        $r = $records;

        if(!file_exists($file)) {
            file_put_contents($file,json_encode($r));
        }
    }
}

echo json_encode($r);

if(isset( $_GET['callback'] )) {
    echo ');';
}

