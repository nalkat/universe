#!/bin/env php
<?php
require_once getenv("ENV_PHPROOT") . "/telemetry/class_telemetry.php";
require_once getenv("ENV_PHPROOT") . "/db/class_db.php";
require_once getenv("ENV_PHPROOT") . "/host/class_host.php";
require_once getenv("ENV_PHPROOT") . "/docker/class_docker.php";
require_once getenv("ENV_PHPROOT") . "/utility/class_utility.php";

if (!isset($GLOBALS['Telemetry'])) $GLOBALS['Telemetry'] = new Telemetry();

$host = new host (false,true,$Telemetry);

$host->gatherHostInfo();
$host->dbUpdate();
printf("%s\n", $host->hostInfo());

?>
