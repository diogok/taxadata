<?php

require '../vendor/autoload.php';

function connect($source) {
  $file = __DIR__.'/../data/'.$source.'.db';
  if(file_exists($file)) {
    $db = new PDO('sqlite:'.__DIR__.'/../data/'.$source.'.db');
    return $db;
  } else {
    throw new Exception("Source not found: ".$source);
  }
}

$app = new \Slim\App;

$app->get("/",function($req,$res){
  header('Location: index.html');
  exit;
  return $res;
});

$app->get("/api/v2/status",function($req,$res) {
  $json =json_encode(["status"=>file_get_contents(__DIR__."/../data/status")]);
  $res->getBody()->write($json);
  return $res;
});

$app->get('/api/v2/sources',function($req,$res) {
  $r = new \StdClass;
  $r->success=true;
  $r->result=[];

  include __DIR__.'/sources.php';
  foreach($sources as $name=>$url) {
    $r->result[] = $name;
  }

  $res->getBody()->write(json_encode($r));
  return $res;
});

$app->get('/api/v2/sources/urls',function($req,$res) {
  $r = new \StdClass;
  $r->success=true;
  $r->result=[];

  include __DIR__.'/sources.php';
  foreach($sources as $name=>$url) {
    $r->result[] = str_replace("archive","resource",$url);
  }

  $res->getBody()->write(json_encode($r));
  return $res;
});

$app->get('/api/v2/{src}/search/species',function($req,$res) {
  $r = new \StdClass;
  $r->success=true;

  $db = connect($req->getAttribute('src'));

  $q = $db->prepare("select * from taxa where scientificName like ? "
                   ."and taxonomicStatus='accepted' order by family, scientificName limit 100;");

  $q->execute(array( "%".$_GET["query"]."%" ));
  $r->result=array();
  while($row = $q->fetchObject()) {
    $r->result[] = $row;
  }

  $res->getBody()->write(json_encode($r));
  return $res;
});

$app->get("/api/v2/{src}/families",function($req,$res) {
  $r = new \StdClass;
  $r->success=true;

  $db = connect($req->getAttribute('src'));

  $query = $db->query("select distinct(family) from taxa order by family;");
  $r->result=array();
  while($row = $query->fetchObject()) {
    if(strlen( $row->family ) > 2) {
      $r->result[] = strtoupper($row->family);
    }
  }

  $res->getBody()->write(json_encode($r));
  return $res;
});

$app->get("/api/v2/{src}/{family}/species",function($req,$res) {
  $r = new \StdClass;
  $r->success=true;

  $db = connect($req->getAttribute('src'));

  $query = $db->prepare("select * from taxa where LOWER(family) = ? order by scientificName;");
  $query->execute(array(strtolower($req->getAttribute('family'))));

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
      if($spp->scientificName == $syn->acceptedNameUsage) {
        $spp->synonyms[] = $syn;
      }
    }
  }

  $res->getBody()->write(json_encode($r));
  return $res;
});

$app->get('/api/v2/{src}/specie/{spp}',function($req,$res){
  $r = new \StdClass;
  $r->success=true;

  $db = connect($req->getAttribute('src'));

  $spp = $req->getAttribute('spp');

  $q = $db->prepare("select * from taxa where scientificName = ? or acceptedNameUsage like ? or scientificNameWithoutAuthorship = ?");
  $q->execute(array($spp,"%".$spp."%",$spp));

  $r->result=null;
  $miss=[];

  while($row = $q->fetchObject()) {
    if($row->taxonomicStatus == 'accepted' && ($row->scientificName == $spp || $row->scientificNameWithoutAuthorship == $spp)) {
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

  $res->getBody()->write(json_encode($r));
  return $res;
});

$app->add(function($req,$res,$next) {
  // CORS
  if(isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Headers: X-Requested-With');
  }

  if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
      header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
  }

  $response = $next($req,$res);

  return $response;

});

$app->add(function($req,$res,$next) {
  // JSON and JSONP
  header('Content-Type: application/json');

  if(isset($_GET['callback'])) {
      echo $_GET['callback'].'(';
  }

  $response = $next($req,$res);

  if(isset( $_GET['callback'] )) {
    echo ');';
  }

  return $response;
});

$app->add(function($req,$res,$next) {
  // error handler
  try {
    $res = $next($req,$res);
  } catch(Exception $e) {
    $res->getBody()->write(json_encode(['success'=>false,'result'=>null,'error'=>$e->getMessage()]));
  }
  return $res;
});

$app->add(function($req,$res,$next){
  // v1 redir
  if(isset($_GET['v1'])) {
    $base = explode('/v1',$_SERVER['REQUEST_URI'])[0].'/v2';
    if($_GET['q'] == '/search/species') {
      header('Location: '.$base.'/flora/search/species?query='.$_GET['query']);
    } else if($_GET['q'] == '/families') {
      header('Location: '.$base.'/flora/families');
    } else if($_GET['q'] == '/species') {
      header('Location: '.$base.'/flora/'.$_GET['family'].'/species');
    } else if($_GET['q'] == '/specie') {
      header('Location: '.$base.'/flora/specie/'.$_GET['scientificName']);
    }
    exit;
  }

  $res = $next($req,$res);
  return $res;
});

$app->run();

