<?php // 8.3.6
if (!defined("PHPROOT")) {
        $detectedRoot = getenv('ENV_PHPROOT');
        if (empty($detectedRoot)) {
                $detectedRoot = __DIR__;
        }
        define("PHPROOT", rtrim($detectedRoot, DIRECTORY_SEPARATOR));
}
?>
