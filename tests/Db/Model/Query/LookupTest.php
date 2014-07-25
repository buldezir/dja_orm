<?php

/**
 * Class LookupTest
 */
class LookupTest extends PHPUnit_Framework_TestCase
{
    public function testFilterLtGt()
    {
        $q = UserModel::objects()->filter(['user_id__gt' => 30, 'user_id__lt' => 40]);
        $obj = $q->current();
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        $this->assertGreaterThan(30, $obj->user_id);
        $this->assertLessThan(40, $obj->user_id);
    }

    public function testFilterFail()
    {
        $this->setExpectedException('\\DomainException');
        $q = CustomerOrderModel::objects()->filter(['user__user_id__gt' => 100]);
    }

    public function testFilterNull()
    {
        $q = UserModel::objects()->filter(['ip' => null]);
        $obj = $q->current();
        $this->assertInstanceOf('\\UserModel', $obj);
        $this->assertNull($obj->ip);

        $q2 = UserModel::objects()->exclude(['ip' => null]);
        $obj2 = $q2->current();
        $this->assertInstanceOf('\\UserModel', $obj2);
        $this->assertNotNull($obj2->ip);
    }

    public function testFilterIn1()
    {
        $q = UserModel::objects()->filter(['user_id__in' => [1, 2, 3, 4, 5]]);
        foreach ($q as $obj) {
            $this->assertContains($obj->user_id, [1, 2, 3, 4, 5]);
        }
    }

    public function testFilterIn2()
    {
        $q = UserModel::objects()->filter(['user_id__in' => Expr('SELECT user_id FROM "user"')]);
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $q->current());
    }

    public function testFilterIn3()
    {
        $q1 = UserModel::objects()->filter(['user_id__in' => [1, 2, 3, 4, 5]])->valuesList('pk', false);
        $q2 = CustomerOrderModel::objects()->filter(['user__in' => $q1]);
        $obj = $q2->current();
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Model', $obj);
        $this->assertContains($obj->user_id, [1, 2, 3, 4, 5]);
    }

    public function testFilterBetween()
    {
        $q = CustomerOrderModel::objects()->filter(['pk__range' => [1, 10]]);
        $this->assertCount(10, $q);
    }

    public function testFilterDate()
    {
        $curYear = intval(date('Y'));
        $curMonth = intval(date('m'));
        $curDay = intval(date('d'));
        $curDow = intval(date('w'));
        $q = CustomerOrderModel::objects()->filter([
            'date_added__year' => $curYear,
            'date_added__month' => $curMonth,
            'date_added__day' => $curDay,
            'date_added__weekday' => $curDow,
        ])->limit(1);
        $this->assertCount(1, $q);
        foreach ($q as $obj) {
            $dt = new DateTime($obj->date_added);
            $this->assertEquals($curYear, $dt->format('Y'));
            $this->assertEquals($curMonth, $dt->format('m'));
            $this->assertEquals($curDay, $dt->format('d'));
            $this->assertEquals($curDow, $dt->format('w'));
        }
    }

    public function testFilterTime()
    {
        $ddt = new DateTime;
        $obj = new CustomerOrderModel([
            'order_number' => 'filter_test1',
            'date_added' => $ddt->format(\Dja\Db\Model\Field\DateTime::FORMAT),
        ]);
        $obj->save();

        $q = CustomerOrderModel::objects()->filter(['date_added__minute' => $ddt->format('i'), 'order_number' => 'filter_test1'])->limit(1);
        $this->assertCount(1, $q);
        foreach ($q as $obj) {
            $dt = new DateTime($obj->date_added);
            $this->assertEquals($ddt, $dt);
        }
    }
}
 