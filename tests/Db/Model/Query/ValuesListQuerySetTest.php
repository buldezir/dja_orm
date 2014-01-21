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
        $q = UserModel::objects()->limit(5)->valuesList('username', 'user_id');
        foreach ($q as $obj) {
            $this->assertInternalType('string', $obj);
        }
        $this->assertCount(5, $q);
    }
}
 