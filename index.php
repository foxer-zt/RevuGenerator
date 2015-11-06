<?php
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */
if (!file_exists('vendor/autoload.php')) {
    throw new Exception('Run "composer install" in project root directory.');
}
require_once 'vendor/autoload.php';
require_once 'RevuGenerator.php';
new RevuGenerator('path/to/file/with/issues', 'path/to/revu/xml/file', 'pattern_for_issue');