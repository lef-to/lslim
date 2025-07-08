<?php

use LSlim\Container;

if (! function_exists('database_path')) {
    function database_path($path = '')
    {
        $c = Container::instance();
        return $c['laravel']->databasePath($path);
    }
}