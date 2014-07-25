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

$log = new Doctrine\DBAL\Logging\DebugStack();
$dbConn->getConfiguration()->setSQLLogger($log);
SqlLog::$log = $log;

class UserModel extends Dja\Db\Model\Model
{
    protected static $dbtable = 'test_user';
    protected static $fields = [
        'user_id' => ['Auto'],
        'username' => ['Char'],
        'fullname' => ['Char'],
        'slug' => ['Slug', 'prepopulate_field' => 'fullname'],
        'email' => ['Email'],
        'password' => ['Char'],
        'date_added' => ['DateTime', 'autoInsert' => true],
        'date_modified' => ['DateTime', 'autoUpdate' => true],
        'is_active' => ['Bool', 'default' => true],
        'ip' => ['Char', 'null' => true],
    ];
}

class CustomerOrderModel extends Dja\Db\Model\Model
{
    protected static $dbtable = 'test_customer_order';
    protected static $fields = [
        'customer_order_id' => ['Auto', 'help_text' => 'первичный ключ'],
        'date_added' => ['DateTime', 'autoInsert' => true, 'help_text' => 'время создания'],
        'user' => ['ForeignKey', 'relationClass' => 'UserModel', 'db_column' => 'user_id', 'null' => true, 'help_text' => 'ссылка на юзера'],
        'order_number' => ['Char', 'null' => true, 'max_length' => 21, 'default' => '', 'blank' => true, 'help_text' => 'полный номер заказа'],
    ];
}

$creation = new \Dja\Db\Creation($dbConn, ['UserModel', 'CustomerOrderModel']);
$creation->processQueueCallback(function (\Dja\Db\Model\Metadata $metadata, \Doctrine\DBAL\Schema\Table $table, array $sql, \Doctrine\DBAL\Connection $db) {
    foreach ($sql as $sqlstmt) {
        $db->exec($sqlstmt);
    }
    $instclass = $metadata->getModelClass();
    switch ($instclass) {
        case 'UserModel':
            for ($i = 1; $i <= 99; $i++) {
                $user = new UserModel([
                    'username' => 'testUser' . $i,
                    'fullname' => 'testUser ' . $i . ' with space',
                    'email' => 'test' . $i . '@ya.ru',
                    'password' => md5('passw' . $i),
                    'ip' => ($i < 90 ? '127.0.0.1' : null),
                ]);
                $user->save();
            }
            break;
        case 'CustomerOrderModel':
            for ($i = 1; $i <= 99; $i++) {
                $order = new CustomerOrderModel([
                    'order_number' => 'testOrder' . $i,
                    'user_id' => $i,
                ]);
                $order->save();
            }
            break;
    }
});

register_shutdown_function(function () use ($dbConn) {
    $dbConn->getSchemaManager()->dropTable(CustomerOrderModel::metadata()->getDbTableName());
    $dbConn->getSchemaManager()->dropTable(UserModel::metadata()->getDbTableName());
    $dbConn->close();
});

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
