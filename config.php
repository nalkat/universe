<?php // 7.3.0-dev

require_once __DIR__ . "/defines/defines.php";
require_once __DIR__ . "/defines.php";
require_once __DIR__ . "/required/class_preRequisites.php";

$required_extensions = <<<DONE
bcmath
date
pcntl
pcre
pgsql
posix
sockets
DONE;

$required_classes = <<<DONE
Logger,/logger/class_logger.php
Timer,/timer/class_timer.php
Utility,/utility/class_utility.php
Universe,/universe/class_universe.php
Galaxy,/universe/class_galaxy.php
System,/universe/class_system.php
SystemObject,/universe/class_systemObject.php
Star,/universe/class_star.php
Planet,/universe/class_planet.php
Continent,/universe/class_continent.php
Country,/universe/class_country.php
City,/universe/class_city.php
House,/universe/class_house.php
Life,/universe/class_life.php
Animal,/universe/class_animal.php
Insect,/universe/class_insect.php
Plant,/universe/class_plant.php
Person,/universe/class_person.php
Skill,/universe/class_skill.php
Job,/universe/class_job.php
Particle,/universe/class_particle.php
Element,/universe/class_element.php
Compound,/universe/class_compound.php
Logger,/logger/class_logger.php
DONE;

foreach (explode(PHP_EOL,$required_extensions) as $required)
{
	PreRequisites::add_extension($required, DEP_REQUIRED);
}

foreach (explode(PHP_EOL, $required_classes) as $required)
{
	list($class_name, $class_path) = explode(',',$required);
	if (!PreRequisites::add_class($class_name, PHPROOT . $class_path, DEP_REQUIRED))
	{
		exit ("Failed to load required definition for $class_name from $class_path" . PHP_EOL);
	}
}
PreRequisites::load_classes();
//PreRequisites::check();
?>
