<?php

/**
 * User: Alexander.Arutyunov
 * Date: 21.01.14
 * Time: 17:30
 */
class ValuesListQuerySetTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $q = UserModel::objects()->valuesList('username', 'user_id')->limit(5);
        foreach ($q as $obj) {
            $this->assertInternalType('string', $obj);
        }
        $this->assertCount(5, $q);
    }
}
 