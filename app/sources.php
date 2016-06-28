<?php

$sources=[];
if(file_exists(__DIR__."/../config/taxadata.ini")) {
  $sources = array_merge($sources,parse_ini_file(__DIR__."/../config/taxadata.ini"));
}
if(file_exists(__DIR__."/config/taxadata.ini")) {
  $sources = array_merge($sources,parse_ini_file(__DIR__."/config/taxadata.ini"));
}
if(file_exists(__DIR__."/taxadata.ini")) {
  $sources = array_merge($sources,parse_ini_file(__DIR__."/taxadata.ini"));
}
if(file_exists(__DIR__."/../taxadata.ini")) {
  $sources = array_merge($sources,parse_ini_file(__DIR__."/../taxadata.ini"));
}
if(file_exists("/etc/biodiv/taxadata.init")) {
  $sources = array_merge($sources,parse_ini_file("/etc/biodiv/taxadata.ini"));
}

