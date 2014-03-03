<?php

/**
 * User: Alexander.Arutyunov
 * Date: 08.11.13
 * Time: 15:24
 */
class RawQuerySetTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $q = UserModel::objects()->raw('SELECT * FROM :t WHERE :pk < 100 LIMIT 5');
        $this->assertCount(5, $q);
    }

    public function testReplacements()
    {
        $q = CustomerOrderModel::objects()->raw('SELECT :pk, :user FROM :t WHERE :user < 100 LIMIT 5');
        $this->assertEquals('SELECT "customer_order_id", "user_id" FROM "customer_order" WHERE "user_id" < 100 LIMIT 5', strval($q));

        $q = CustomerOrderModel::objects()->raw('SELECT * FROM :t');
        $this->assertEquals('SELECT "customer_order_id", "date_added", "user_id", "order_number" FROM "customer_order"', strval($q));
    }

    public function testBind()
    {
        $q = UserModel::objects()->raw('SELECT * FROM :t WHERE :pk < :maxPK LIMIT 5', [':maxPK' => 100]);
        $this->assertCount(5, $q);
    }

    public function testSelectingModels()
    {
        $q = UserModel::objects()->raw('SELECT * FROM :t LIMIT 5');
        foreach ($q as $obj) {
            $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        }
    }

    public function testSelectingArrays()
    {
        $pkCol = UserModel::metadata()->getPrimaryKey();
        $q = UserModel::objects()->raw('SELECT * FROM :t LIMIT 5')->returnValues();
        foreach ($q as $obj) {
            $this->assertInternalType('array', $obj);
            $this->assertArrayHasKey($pkCol, $obj);
        }
    }

    public function testSelectingCached()
    {
        $countQ1 = count(SqlLog::$log->queries);
        $q = UserModel::objects()->raw('SELECT * FROM :t LIMIT 5');
        foreach ($q->cached() as $obj) {
            $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        }
        foreach ($q->cached() as $obj) {
            $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        }
        $countQ2 = count(SqlLog::$log->queries);
        $this->assertEquals(1, $countQ2 - $countQ1);
    }

    public static function tearDownAfterClass()
    {
        //SqlLog::dump();
    }
}
