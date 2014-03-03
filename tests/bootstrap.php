<?php

defined('DJA_PATH') || define('DJA_PATH', realpath(__DIR__ . '/../src'));
defined('DJA_APP_PATH') || define('DJA_APP_PATH', realpath(__DIR__ . '/../htdocs'));

/** @var $loader \Composer\Autoload\ClassLoader */
$loader = require_once __DIR__ . '/../vendor/autoload.php';

/**
 * strrrraaaaangeee
 * PHPUnit_Extensions_Story
 */
//require_once '/Users/sasha/projects/php/phpunit/phpunit-story-master/PHPUnit/Extensions/Story/Autoload.php';

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

$log = new Doctrine\DBAL\Logging\DebugStack();
$dbConn->getConfiguration()->setSQLLogger($log);
SqlLog::$log = $log;

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
        'is_active' => ['Bool', 'default' => true],
        'ip' => ['Char'],
    ];
}
class CustomerOrderModel extends Dja\Db\Model\Model
{
    protected static $dbtable = 'customer_order';
    protected static $fields = [
        'customer_order_id' => ['Auto', 'help_text' => 'первичный ключ'],
        'date_added' => ['DateTime', 'null' => true, 'help_text' => 'время создания'],
        'user' => ['ForeignKey', 'relationClass' => 'UserModel', 'db_column' => 'user_id', 'null' => true, 'help_text' => 'ссылка на ответственного менеджера БО'],
        'order_number' => ['Char', 'null' => true, 'max_length' => 21, 'default' => '', 'blank' => true, 'help_text' => 'полный номер заказа'],
    ];
}

class SqlLog
{
    /**
     * @var \Doctrine\DBAL\Logging\DebugStack
     */
    public static $log;

    public static function dump()
    {
        array_walk(self::$log->queries, function ($q) {
            $sql = is_array($q['params']) ? strtr($q['sql'], $q['params']) : $q['sql'];
            echo $sql . PHP_EOL;
        });
    }
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
	"firstname" varchar(32) DEFAULT ''::character varying COLLATE "default",
	"lastname" varchar(32) DEFAULT ''::character varying COLLATE "default",
	"email" varchar(96) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"ip" varchar(15) NOT NULL DEFAULT ''::character varying COLLATE "default",
	"date_added" timestamp(6) NOT NULL,
	"date_modified" timestamp(6) NOT NULL,
	"is_active" bool NOT NULL DEFAULT true,
	CONSTRAINT "user_pkey" PRIMARY KEY ("user_id") NOT DEFERRABLE INITIALLY IMMEDIATE
)
 */