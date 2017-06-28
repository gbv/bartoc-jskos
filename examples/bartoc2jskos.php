<?php

/**
 * Query BARTOC via command line.
 */

if (php_sapi_name() != "cli") exit;
if (count($argv) < 2) {
    print "usage: php {$argv[0]} URI|ID\n";
    exit;
}

$uri = $argv[1];
if (preg_match('/^\d+$/', $uri)) {
    $uri = "http://bartoc.org/en/node/$uri";
}

require __DIR__ . '/../vendor/autoload.php';

$service = new \BARTOC\JSKOS\Service();
$jskos = $service->queryURI($uri);
if ($jskos) {
    print $jskos->json() . "\n";
}
