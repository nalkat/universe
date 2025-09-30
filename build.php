<?php // 8.3.6

//require_once __DIR__ . "/telemetry/class_telemetry.php";
require_once __DIR__ . "/config.php";
Utility::init();
$C = new Logger("logs/consoe.log",true);
$A = new Logger("logs/access.log", true);
$E = new Logger("logs/error.log", true); 
$D = new Logger("logs/debug.log", true);
Utility::setLog($C, L_CONSOLE);
Utility::setLog($A, L_ACCESS);
Utility::setLog($E, L_ERROR);
Utility::setLog($D, L_DEBUG);

Universe::init();
// Create a cube universe for testing ...
Universe::setMaxX (10000);
Universe::setMaxY (10000);
Universe::setMaxZ (10000);
// set day length
Universe::setRotationSpeed (100);
// set expansion rate
Universe::setExpansionRate (2.47, 1.22, 4.0);

echo "MAX SIZE: " . Universe::getMaxSize() . PHP_EOL;
$expansionRate = Universe::getExpansionRate();
echo "Expansion rate: x=> " . $expansionRate['x'] . ", y=> " . $expansionRate['y'] . ", z=> " . $expansionRate['z'] . PHP_EOL;
Universe::setLocation (5000,5000,5000);

$u = new Universe("Marstellar", Universe::getMaxX(), Universe::getMaxY(), Universe::getMaxZ());
$u->dump();
//exit;
$u->grow($expansionRate['x'], $expansionRate['y'], $expansionRate['z']);
echo "CUR SIZE: " . Universe::getCurrentSize() . PHP_EOL;
echo "Cur X: " . Universe::getCurrentX() . PHP_EOL;
echo "Cur Y: " . Universe::getCurrentY() . PHP_EOL;
echo "Cur Z: " . Universe::getCurrentZ() . PHP_EOL;
$loc = Universe::getLocation();
echo "Location: x=>{$loc['x']} y=>{$loc['y']} z=>{$loc['z']}" . PHP_EOL;

$u->createGalaxy ("Permeoid",42.323, 44.131, 20.12);
$u->createGalaxy ("Andromeda",45.333, 48.313, 55.98);
$u->galaxyList();
//var_dump($u->galaxies);
var_dump ($u);
Utility::write("The Universe has expanded its x-plane by " . $expansionRate['x'] . " units", LOG_INFO, L_CONSOLE);
Utility::write("Cur X: " . Universe::getCurrentX() . PHP_EOL, LOG_INFO, L_CONSOLE);
Utility::write("Cur Y: " . Universe::getCurrentY() . PHP_EOL, LOG_INFO, L_CONSOLE);
Utility::write("Cur Z: " . Universe::getCurrentZ() . PHP_EOL, LOG_INFO, L_CONSOLE);
Utility::write("numObjects in Universe: " . Universe::$numObjects . PHP_EOL, LOG_INFO, L_CONSOLE);
echo "numObjects in Universe: " . count(Universe::$objectList) . PHP_EOL;
?>
