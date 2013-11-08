<?php

defined('DJA_PATH') || define('DJA_PATH', realpath(__DIR__.'/../src'));
defined('DJA_APP_PATH') || define('DJA_APP_PATH', realpath(__DIR__.'/../htdocs'));

/** @var $loader \Composer\Autoload\ClassLoader */
$loader = require_once __DIR__.'/../vendor/autoload.php';
//$loader = require_once __DIR__.'/../../sensiolab/autoload.php';
//$loader->add('Dja', DJA_PATH);
//$loader->add('PHPUnit_Extensions_Story', '/Users/sasha/projects/php/phpunit/phpunit-story-master/PHPUnit/Extensions/Story/');
$loader->loadClass('Dja\\Util\\Functions');

/**
 * PHPUnit_Extensions_Story
 */
require_once '/Users/sasha/projects/php/phpunit/phpunit-story-master/PHPUnit/Extensions/Story/Autoload.php';
