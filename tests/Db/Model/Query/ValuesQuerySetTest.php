<?php

/**
 * User: Alexander.Arutyunov
 * Date: 21.01.14
 * Time: 17:25
 */
class ValuesQuerySetTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $pkCol = UserModel::metadata()->getPrimaryKey();
        $q = UserModel::objects()->values()->limit(5);
        foreach ($q as $obj) {
            $this->assertInternalType('array', $obj);
            $this->assertArrayHasKey($pkCol, $obj);
        }
        $this->assertCount(5, $q);
    }

    public function testCustomeFields()
    {
        $q = UserModel::objects()->limit(5)->values(['user_id', 'username']);
        foreach ($q as $obj) {
            $this->assertCount(2, $obj);
        }
    }
}
 