<?php

/**
 * Class ValuesListQuerySetTest
 */
class ValuesListQuerySetTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $q = CustomerOrderModel::objects()->valuesList('order_number', 'order_number')->limit(5);
        foreach ($q as $key => $obj) {
            $this->assertInternalType('string', $obj);
            $this->assertEquals($key, $obj);
        }
        $this->assertCount(5, $q);
    }
}
 