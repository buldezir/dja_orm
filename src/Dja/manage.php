#!/usr/bin/php
<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.08.13
 * Time: 11:38
 */
echo "[manage console]\n";

$currentDir = $_SERVER['PWD'];

define('DJA_PATH', realpath(__DIR__ . '/../src'));
define('DJA_APP_PATH', realpath($currentDir));