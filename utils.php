<?php

$ini = parse_ini_file(__DIR__."/config.ini");
foreach($ini as $k=>$v) {
    if(!defined($k)) {
        define($k,$v);
    }
}

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

function http_get($url) {
    return json_decode(file_get_contents($url));
}

function http_post($url,$data) {
    $opts = ['http'=>['method'=>'POST','content'=>json_encode($data),'header'=>'Content-type: application/json']];
    $r = file_get_contents($url, NULL, stream_context_create($opts));
    return json_decode($r);
}


