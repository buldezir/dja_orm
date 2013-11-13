<?php
/**
 * User: Alexander.Arutyunov
 * Date: 08.11.13
 * Time: 15:24
 */

class QueryTest extends PHPUnit_Framework_TestCase {

    public function test()
    {
        $q = UserModel::objects()->limit(5);
        $this->assertEquals(5, $q->count());
    }
}
