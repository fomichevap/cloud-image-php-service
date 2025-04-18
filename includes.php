<?php
/**
 * Connection file: loads configuration and sets up class autoloading.
 */

// Load the configuration.
require_once __DIR__ . '/config.php';

/**
 * Class autoloader.
 * It is assumed that all classes are located in the 'classes' directory,
 * and the file name corresponds to the class name plus ".php".
 */
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        if (DEBUG) {
            error_log("Autoloader: Failed to load class {$class} from file {$file}");
        }
    }
});

// You can include helper functions or additional files if needed.
// require_once __DIR__ . '/helpers.php';
