<?php

require __DIR__ . '/../vendor/autoload.php';

$id = $argv[1];

$service = new \BARTOC\JSKOS\Service();

$jskos = $service->queryURI("http://bartoc.org/en/node/$id");
if ($jskos) {
    print $jskos->json() . "\n";
}
