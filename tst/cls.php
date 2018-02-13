#!/usr/bin/env php56
<?php
    // register autoloading of classes
    spl_autoload_register('AutoLoader');
    function AutoLoader($className) {
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        require_once __DIR__.DIRECTORY_SEPARATOR . $file . '.php';
    }

    use App\CPPAnalyzer;

    new CPPAnalyzer($argc, $argv);
?>
