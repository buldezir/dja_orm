<?php

/**
 * User: Alexander.Arutyunov
 * Date: 08.11.13
 * Time: 15:24
 */
class QuerySetTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $q = UserModel::objects();
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $q->all()->current());
    }

    public function testLimit()
    {
        $q = UserModel::objects()->limit(5);
        $this->assertCount(5, $q);
    }

    public function testFilterCommon()
    {
        $q = UserModel::objects()->filter(['user_id__gt' => 30, 'user_id__lt' => 40]);
        $obj = $q->current();
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        $this->assertGreaterThan(30, $obj->user_id);
        $this->assertLessThan(40, $obj->user_id);
    }

    public function testFilterNull()
    {
        $q = UserModel::objects()->filter(['lastname' => null]);
//        echo PHP_EOL . $q . PHP_EOL;
//        echo PHP_EOL . $q->count() . PHP_EOL;
//        var_dump($q->current()->toArray());
        $this->assertNull($q->current()->lastname);

        $q2 = UserModel::objects()->exclude(['lastname' => null]);
        $this->assertNotNull($q2->current()->lastname);
    }

    public function testFilterIn1()
    {
        $q = UserModel::objects()->filter(['user_id__in' => [1, 2, 3, 4, 5]]);
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $q->current());
    }

    public function testFilterIn2()
    {
        $q = UserModel::objects()->filter(['user_id__in' => Expr('SELECT user_id FROM "user"')]);
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $q->current());
    }

    public function testSelectingCached()
    {
        $countQ1 = count(SqlLog::$log->queries);
        $q = UserModel::objects()->limit(5);
        $q->count();
        foreach ($q->cached() as $obj) {
            $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        }
        foreach ($q->cached() as $obj) {
            $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        }
        $countQ2 = count(SqlLog::$log->queries);
        $this->assertEquals(1, $countQ2 - $countQ1);
    }
}
