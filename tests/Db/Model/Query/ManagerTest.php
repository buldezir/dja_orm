<?php

/**
 * User: Alexander.Arutyunov
 * Date: 06.03.14
 * Time: 12:00
 */
class ManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testNewInstance()
    {
        $this->setExpectedException('\\InvalidArgumentException');
        $m = new \Dja\Db\Model\Query\Manager('\Dja\Db\Model\Model');
    }

    public function testGetOrCreate()
    {
        $obj = UserModel::objects()->getOrCreate(['user_id' => 1]);
        $this->assertInstanceOf('\\UserModel', $obj);
        $this->assertFalse($obj->isNewRecord());

        $obj = UserModel::objects()->getOrCreate(['user_id' => 99999]);
        $this->assertInstanceOf('\\UserModel', $obj);
        $this->assertTrue($obj->isNewRecord());
    }

//    public function testDoUpdateInsertFail()
//    {
//        $this->setExpectedException('\\LogicException');
//        UserModel::objects()->doDelete();
//        UserModel::objects()->doUpdate([]);
//    }
}
 