<?php
require_once __DIR__ . "/class_envision.php";

// Main loader begins here
//require_once "../nse/class_nse.php";
// I don't care about this next line.  Even if I want to rely on the ENV being
// installed, at this point in the game, it is not super beneficial to go searching and
// and patching.  Let bashrc do its thing and we will do ours.  If it's there, great!, if not
// no big deal, just have to employ another discovery method. I don't know if I am going to
// incorporate NSE into this analytics engine or not.. Probably will eventually.
// if (($_ENV['ENV_INSTALLED'] === "1") && (!empty($_ENV['ENV_PHPROOT']))) {
//  require_once $_ENV["ENV_PHPROOT"] . "/nse/class_nse.php";

$GLOBALS['EnVision'] ?? $GLOBALS['EnVision'] = new EnVision();
$EnVision =& $GLOBALS['EnVision'];

if (!isset($_ENV) || empty($_ENV)) $_ENV=getenv();
// if we don't have an EnVision object, let's try to find one or create one.
if ((!isset($GLOBALS['EnVision'])) || (!is_a($GLOBALS['EnVision'], "EnVision"))) {
        // if the file exists, try to unserialize it in to an object
        if (file_exists($_ENV["ENV_VISIONDIR"] . "/objects/cereal")) {
                $GLOBALS['EnVision'] = new EnVision($_ENV['ENV_VISIONDIR'] . "/objects/cereal",true);
                $GLOBALS['EnVision']->objects_unserialized++;
        }
        if (!(is_a($GLOBALS['EnVision'],"EnVision"))) {
                $GLOBALS["EnVision"] = new EnVision();
        }
//      else {
                // failed, just make a new one
///             $GLOBALS['EnVision'] = new EnVision();
//      }
        // since we're done, let's increment everything and save it for the next call.
        $GLOBALS['EnVision']->objects_instantiated++;
        $GLOBALS['EnVision']->files_opened++;
        $GLOBALS['EnVision']->objects_serialized++;
        $GLOBALS['EnVision']->functions_entered++;
        $GLOBALS['EnVision']->functions_left++;
        // the serialize function uses file locking to write out the data in an attempt to
        // avoid coliding with any other processes which may be running at the same time.
        $GLOBALS['EnVision']->serialize($GLOBALS['EnVision']);
//var_export($GLOBALS['EnVision']);
        // not sure if exit is appropriate here due to the fact that it could interfere with
        // the web services too...  ANYTHING php is going to be running this, so we have to
        // think about that type of thing in our design.  Minimally invasive, but collect as
        // much data as feasibly possible.
        return (0);
} else {
//var_export($GLOBALS['EnVision']);
        $GLOBALS['EnVision']->functions_entered++;
        $GLOBALS['EnVision']->functions_left++;
        $GLOBALS['EnVision']->objects_serialized++;
        $GLOBALS['EnVision']->serialize($GLOBALS['EnVision']);
        return (0);
}
?>
