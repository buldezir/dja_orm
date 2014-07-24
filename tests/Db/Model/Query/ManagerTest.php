<?php

/**
 * Class ManagerTest
 */
class ManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testNewInstance()
    {
        $this->setExpectedException('\\InvalidArgumentException');
        $m = new \Dja\Db\Model\Query\Manager('\Dja\Db\Model\Model');
    }

    public function testAccess()
    {
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Query\\Manager', UserModel::objects());
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Query\\QuerySet', UserModel::objects()->all());
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
}
 