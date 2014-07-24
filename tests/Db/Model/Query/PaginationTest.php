<?php

/**
 * Class PaginationTest
 */
class PaginationTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $q = CustomerOrderModel::objects();
        $paginator = new \Dja\Db\Model\Query\Pagination($q, 3, 10);
        $this->assertEquals(10, $paginator->getItemsPerPage());
        $this->assertEquals(3, $paginator->getCurrentPage());
    }

    public function testCounters()
    {
        $q = CustomerOrderModel::objects();
        $paginator = new \Dja\Db\Model\Query\Pagination($q, 3, 10);
        $this->assertEquals(CustomerOrderModel::objects()->doCount(), $paginator->getNumItems());
        $this->assertEquals($paginator->getQuerySet()->count(), $paginator->getItemsPerPage());
    }

    public function testIter()
    {
        $q = CustomerOrderModel::objects();
        $paginator = new \Dja\Db\Model\Query\Pagination($q, 3, 10);
        foreach ($paginator as $obj) {
            $this->assertInstanceOf('\\CustomerOrderModel', $obj);
        }
    }
}
