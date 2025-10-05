<?php // 7.3.0 - required/config.php
// This project now runs entirely from its repository without requiring a shared
// environment bootstrap.
require_once __DIR__ . "/required/defines.php";
require_once __DIR__ . "/required/class_preRequisites.php";

// while this class does not have any extension dependencies, the following is an example
// of how to use this class:

// The singular method to add a *required* extension:
PreRequisites::add_extension('pgsql', DEP_REQUIRED);
// The singular method to add an *optional* extension:
PreRequisites::add_extension('sqlite3', DEP_OPTIONAL);

/* The quicker method:
 * create a list of dependent php extensions, 1 per line
 * use a "foreach" iterator to load the specified extension
 * using the list
 */
$extensions = <<<DONE
fileinfo
pcre
pgsql
socket
sqlite3
xmlrpc
DONE;

// while I find it easier to read the above vertical list,
// it would be possible to also use a character-separated
// string as well by changing "PHP_EOL" below with the
// desired character.
foreach (explode(PHP_EOL,$extensions) as $extension)
{
	PreRequisites::add_extension($extension,DEP_REQUIRED);
}

// The singular method to add a *required* class:
PreRequisites::add_class("Logger","/logger/class_logger.php",DEP_REQUIRED);
// The singular method to add an *optional* extension:

/* The quicker method:
 * create a list of dependent php classes, 1 per line, as above,
 * but now, we require the class name AND the path to the defintion.
 * Also, as above, you can use whatever separation token as long the
 * format is "classname,/path/to/definition"
 */
$required_classes = <<<DONE
Logger,/logger/class_logger.php
Utility,/utility/class_utility.php
MetadataStore,/utility/class_metadataStore.php
DONE;

foreach (explode(PHP_EOL,$required_classes) as $required)
{
	list($class_name, $class_path) = explode(',',$required);
	if(!PreRequisites::add_class($class_name,PHPROOT . $class_path,DEP_REQUIRED))
	{
		exit ("Failed to load required definition for $class_name from $class_path" . PHP_EOL);
	}
}

//now we need to make sure that the *required* class definitions will load:
PreRequisites::load_classes();

// now we need to ensure the *required* extensions are installed
PreRequisites::check();
?>
