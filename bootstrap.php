<?php
/******************
 *    ERRORS      *
 *****************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/******************
 *    LOADER      *
 *****************/
spl_autoload_register(function ($class) {
    $dirs = array('/app/', '/core/');
    foreach ($dirs as $dir) {
        $file = __DIR__.$dir.str_replace(array('\\', '_'), '/', $class).'.php';

        if (file_exists($file)) {
            require_once $file;

            return true;
        }
    }
});

/******************
 *    CONFIG      *
 *****************/
require_once __DIR__.'/Config.php';
Request::$base_url = Config::BASE_URL;

/******************
 *    ROUTER      *
 *****************/
 require_once __DIR__.'/router.php';
