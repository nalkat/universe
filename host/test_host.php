#!/bin/env php
<?php
require_once getenv("ENV_PHPROOT") . "/EnVision/class_envision.php";
require_once getenv("ENV_PHPROOT") . "/db/class_db.php";
require_once getenv("ENV_PHPROOT") . "/host/class_host.php";
require_once getenv("ENV_PHPROOT") . "/docker/class_docker.php";
require_once getenv("ENV_PHPROOT") . "/utility/class_utility.php";

if (!isset($GLOBALS['EnVision'])) $GLOBALS['EnVision'] = new EnVision();

$host = new host (false,true,$EnVision);

$host->gatherHostInfo();
$host->dbUpdate();
printf("%s\n", $host->hostInfo());

?>
