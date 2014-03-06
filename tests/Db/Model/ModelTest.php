<?php

class ModelTest extends PHPUnit_Framework_TestCase
{
    public function testHydrateAndLazyRel()
    {
        $countQ1 = count(SqlLog::$log->queries);
        $obj = new CustomerOrderModel([
            'customer_order_id' => 1,
            'user_id' => 1,
            'order_number' => 'asdasd1123123',
            'date_added' => date('Y-m-d H:i:s'),
        ], false);

        $this->assertInstanceOf('\\UserModel', $obj->user);
        $countQ2 = count(SqlLog::$log->queries);
        $this->assertEquals(1, $countQ2 - $countQ1, 'Must be 1 new query because provided only foreign key value');

        $obj = new CustomerOrderModel([
            'customer_order_id' => 1,
            'user_id' => 1,
            'order_number' => 'asdasd1123123',
            'date_added' => date('Y-m-d H:i:s'),
            'user__user_id' => 1,
            'user__username' => 'test',
        ], false);

        $this->assertInstanceOf('\\UserModel', $obj->user);
        $this->assertEquals('test', $obj->user->username);
        $countQ3 = count(SqlLog::$log->queries);
        $this->assertEquals(0, $countQ3 - $countQ2, 'Must be no new queries because related object data provided for hydration');
    }

    public function testSetRelObject()
    {
        $obj1 = new CustomerOrderModel();
        $relObj1 = new UserModel([
            'user_id' => 1,
        ]);
        $obj1->user = $relObj1;
        $this->assertEquals($relObj1->user_id, $obj1->user_id);
    }

    public function testSetupDefaultValues()
    {
        $obj = new UserModel();
        $this->assertEquals(true, $obj->is_active);

        UserModel::metadata()->getField('is_active')->setOption('default', false);

        $obj = new UserModel();
        $this->assertEquals(false, $obj->is_active);
    }

    public function testValidation()
    {
        $obj = new UserModel();
        $obj->email = 'zzzzzzzz@ya.ru';
        $v = $obj->validate();
        $this->assertCount(0, $v, validationErrorsToString($v));
    }

    public function testRefresh()
    {
        $obj = UserModel::objects()->get(1);
        $initial = $obj->username;
        $obj->username = 'testtest';
        $obj->refresh();
        $this->assertEquals($initial, $obj->username);
    }

    public function testToArray()
    {
        /** @var CustomerOrderModel $co */
        $co = CustomerOrderModel::objects()->exclude(['user' => null, 'user_id' => 0])->limit(1)->current();
        $arr = $co->toArray(2);
        $this->assertArrayHasKey('user', $arr);
        $this->assertSame($co->user_id, $arr['user']);

        $co = CustomerOrderModel::objects()->exclude(['user' => null, 'user_id' => 0])->limit(1)->selectRelated(['user'])->current();
        $arr = $co->toArray(2);
        $this->assertArrayHasKey('user', $arr);
        $this->assertInternalType('array', $arr['user']);
    }
}