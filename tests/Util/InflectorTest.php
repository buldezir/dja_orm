<?php

/**
 * Class InflectorTest
 */
class InflectorTest extends PHPUnit_Framework_TestCase
{
    public function testTableize()
    {
        $this->assertEquals('user_roles', \Dja\Util\Inflector::tableize('\\UserRoles'));
    }

    public function testNamespacedTableize()
    {
        $this->assertEquals('user_roles', \Dja\Util\Inflector::namespacedTableize('\\App\\Models\\UserRoles'));
        $this->assertEquals('user_roles', \Dja\Util\Inflector::namespacedTableize('\\Application\\Models\\UserRoles'));

        $this->assertEquals('some_app_user_roles', \Dja\Util\Inflector::namespacedTableize('\\SomeApp\\Models\\UserRoles'));
    }

    public function testClassify()
    {
        $this->assertEquals('TableName', \Dja\Util\Inflector::classify('table_name'));
    }

    public function testCamelize()
    {
        $this->assertEquals('myTableName', \Dja\Util\Inflector::camelize('my_table_name'));
    }
}
 