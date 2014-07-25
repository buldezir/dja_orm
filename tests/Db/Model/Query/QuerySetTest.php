<?php

/**
 * Class QuerySetTest
 */
class QuerySetTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $q = UserModel::objects();
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $q->all()->current());
    }

    public function testGetFailArg()
    {
        $this->setExpectedException('\\InvalidArgumentException');
        $obj = UserModel::objects()->get('bad arg');
    }

    public function testGetFailFound()
    {
        $this->setExpectedException('\\RuntimeException');
        $obj = UserModel::objects()->get(99999);
    }

    public function testLimit()
    {
        $q = UserModel::objects()->limit(5);
        $this->assertCount(5, $q);
    }

    public function testDoCount()
    {
        $q = UserModel::objects()->doCount();
        $this->assertInternalType('int', $q);

        $q = UserModel::objects()->doCount(true);
        $this->assertInternalType('int', $q);
    }

    public function testOrder()
    {
        $q = UserModel::objects()->order(['-username']);
        $this->assertContains('ORDER BY "t"."username" DESC', strval($q));
    }

    public function testOrderFail()
    {
        $this->setExpectedException('\\DomainException');
        $q = CustomerOrderModel::objects()->order(['user__user_id']);
    }

    public function testPager()
    {
        $q1 = UserModel::objects()->all()->byPage(1, 10);
        $this->assertCount(10, $q1);

        $q2 = UserModel::objects()->all()->byPage(2, 5);
        $this->assertCount(5, $q2);
    }

    public function testOffsets()
    {
        $q = UserModel::objects()->all()[1];
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $q);

        $q = UserModel::objects()->all()['10:10'];
        $this->assertCount(10, $q);
    }

    public function testOffsetsFailArg()
    {
        $this->setExpectedException('\\InvalidArgumentException');
        $q = UserModel::objects()->all()['10-10'];
    }

    public function testSelectRelated()
    {
        $countQ1 = count(SqlLog::$log->queries);
        $q = CustomerOrderModel::objects()->selectRelated(['depth' => 1])->filter(['user__user_id__gte' => 1])->limit(10)->order(['-user__user_id']);
        foreach ($q as $obj) {
            if ($obj->user) {
                $this->assertInstanceOf('\\UserModel', $obj->user);
            } else {
                $this->assertNull($obj->user);
            }
        }
        $countQ2 = count(SqlLog::$log->queries);
        $this->assertEquals(1, $countQ2 - $countQ1);
    }

    public function testSelectRelatedDecrementDepth()
    {
        $this->setExpectedException('\\InvalidArgumentException');
        $q = CustomerOrderModel::objects()->selectRelated(['depth' => 3])->selectRelated(['depth' => 1]);
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

    public function testSelectingNotCached()
    {
        $countQ1 = count(SqlLog::$log->queries);
        $q = UserModel::objects()->limit(5);
        $q->count();
        foreach ($q as $obj) {

        }
        $q->count();
        foreach ($q as $obj) {

        }
        $q->count();
        $countQ2 = count(SqlLog::$log->queries);
        $this->assertEquals(2, $countQ2 - $countQ1);
    }
}
