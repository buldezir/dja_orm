<?php

class VirtualTest extends PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $mtd = CustomerOrderModel::metadata();

        $mtd->addField('vrt', new \Dja\Db\Model\Field\Virtual([
            'getter' => function (\CustomerOrderModel $model) {
                    return '#' . $model->pk . ' ' . $model->order_number;
                }
        ]));

        $qs = CustomerOrderModel::objects()->all();
        /** @var CustomerOrderModel $model */
        $model = $qs->current();

        $this->assertEquals('#' . $model->pk . ' ' . $model->order_number, $model->vrt);
    }
}
 