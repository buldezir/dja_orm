<?php

defined('DJA_PATH') || define('DJA_PATH', realpath(__DIR__ . '/../src'));
defined('DJA_APP_PATH') || define('DJA_APP_PATH', realpath(__DIR__ . '/../htdocs'));

/** @var $loader \Composer\Autoload\ClassLoader */
$loader = require_once __DIR__ . '/../vendor/autoload.php';

/**
 * PHPUnit_Extensions_Story
 */
require_once '/Users/sasha/projects/php/phpunit/phpunit-story-master/PHPUnit/Extensions/Story/Autoload.php';

$dbConn = \Doctrine\DBAL\DriverManager::getConnection(array(
    'driver' => 'pdo_pgsql',
    'dbname' => 'sasha',
    'user' => 'sasha',
    'password' => '',
    'host' => 'localhost',
));
Dja\Db\Model\Metadata::setDefaultDbConnection($dbConn);

register_shutdown_function(function () use ($dbConn) {
    $dbConn->close();
});

class UserModel extends Dja\Db\Model\Model
{
    protected static $dbtable = 'user';

    protected static $fields = [
        'user_id' => ['Auto'],
        'username' => ['Char'],
        'email' => ['Email'],
        'password' => ['Char'],
        'firstname' => ['Char', 'default' => ''],
        'lastname' => ['Char', 'default' => ''],
        'date_added' => ['DateTime', 'autoInsert' => true],
        'date_modified' => ['DateTime', 'autoUpdate' => true],
        'is_active' => ['Bool'],
        'ip' => ['Char'],
    ];
}

function validationErrorsToString(array $validationErrors)
{
    $result = '';
    foreach ($validationErrors as $field => $errors) {
        $result .= "[$field : " . implode(', ', $errors) . "]\n";
    }
    return $result;
}

/*
CREATE TABLE "public"."user" (
	"user_id" serial NOT NULL,
	"username" varchar(255) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"password" varchar(255) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"firstname" varchar(32) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"lastname" varchar(32) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"email" varchar(96) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"ip" varchar(15) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"date_added" timestamp(6) NOT NULL,
	"date_modified" timestamp(6) NOT NULL,
	"is_active" bool NOT NULL DEFAULT true,
	CONSTRAINT "user_pkey" PRIMARY KEY ("user_id") NOT DEFERRABLE INITIALLY IMMEDIATE
)
 */