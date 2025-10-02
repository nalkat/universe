<?php // 7.3.0-dev

require_once __DIR__ . "/defines/defines.php";
require_once __DIR__ . "/defines.php";
require_once __DIR__ . "/required/class_preRequisites.php";

if (!function_exists('universe_project_path')) {
        function universe_project_path(string $relativePath) : string
        {
                $root = rtrim(PHPROOT, DIRECTORY_SEPARATOR);
                return $root . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
        }
}

$required_extensions = <<<DONE
bcmath
date
pcntl
pcre
pgsql
posix
sockets
sqlite3
DONE;

$required_classes = <<<DONE
Telemetry,telemetry/class_telemetry.php
Logger,logger/class_logger.php
Timer,timer/class_timer.php
Utility,utility/class_utility.php
MetadataStore,utility/class_metadataStore.php
LoreForge,utility/class_loreForge.php
Universe,class_universe.php
UniverseDaemon,class_universeDaemon.php
UniverseSimulator,class_universeSimulator.php
Galaxy,class_galaxy.php
System,class_system.php
SystemObject,class_systemObject.php
Star,class_star.php
Planet,class_planet.php
Structure,class_structure.php
Settlement,class_settlement.php
Continent,class_continent.php
Country,class_country.php
City,class_city.php
House,class_house.php
Life,class_life.php
Animal,class_animal.php
Insect,class_insect.php
Plant,class_plant.php
Person,class_person.php
Skill,class_skill.php
Job,class_job.php
Particle,class_particle.php
Element,class_element.php
Compound,class_compound.php
DONE;

foreach (explode(PHP_EOL,$required_extensions) as $required)
{
	PreRequisites::add_extension($required, DEP_REQUIRED);
}

foreach (explode(PHP_EOL, $required_classes) as $required)
{
	list($class_name, $class_path) = explode(',',$required);
        if (!PreRequisites::add_class($class_name, universe_project_path($class_path), DEP_REQUIRED))
	{
		exit ("Failed to load required definition for $class_name from $class_path" . PHP_EOL);
	}
}
PreRequisites::load_classes();
//PreRequisites::check();
?>
