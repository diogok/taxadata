<?php

$sources=[];

if(file_exists("/etc/biodiv/taxadata.init")) {
  $sources = array_merge($sources,parse_ini_file("/etc/biodiv/taxadata.ini"));
} else if(file_exists(__DIR__."/../config/taxadata.ini")) {
  $sources = array_merge($sources,parse_ini_file(__DIR__."/../config/taxadata.ini"));
}


