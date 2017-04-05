<?php

include 'vendor/autoload.php';

$service = new \BARTOC\JSKOS\Service();
\JSKOS\Server::runService($service);
