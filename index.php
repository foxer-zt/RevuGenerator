<?php
if (!file_exists('vendor/autoload.php')) {
    throw new Exception('Run "composer install" in project root directory.');
}
require_once 'vendor/autoload.php';
require_once 'Settings.php';
require_once 'RevuGenerator.php';
new RevuGenerator();
