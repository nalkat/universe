<?php // 7.3.0-dev

require_once __DIR__ . "/EnVision/class_envision.php";
require_once __DIR__ . "/config.php";
Utility::init();
$C = new Logger("logs/console.log",true);
$A = new Logger("logs/access.log", true);
$E = new Logger("logs/error.log", true);
$D = new Logger("logs/debug.log", true);
Utility::setLog($C, L_CONSOLE);
Utility::setLog($A, L_ACCESS);
Utility::setLog($E, L_ERROR);
Utility::setLog($D, L_DEBUG);
Universe::init();

Universe::setMaxX (10000);
Universe::setMaxY (10000);
Universe::setMaxZ (10000);
echo "MAX SIZE: " . Universe::getMaxSize() . PHP_EOL;
echo "CUR SIZE: " . Universe::getCurrentSize() . PHP_EOL;

//$u = new Universe("Marstellar",992.3,772.2,330.8);

require_once __DIR__ . "/build.php";

while (true) {
	$u->tick();
	var_export($u);
	sleep(5);
	echo $u->getTicks() . PHP_EOL;
}
?>
