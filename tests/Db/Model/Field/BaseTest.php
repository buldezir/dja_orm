<?php

/**
 * Class BaseTest
 */
class BaseTest extends \PHPUnit_Framework_TestCase
{
    public function testViewValue()
    {
        $f = new \Dja\Db\Model\Field\Char([
            'name' => 'test',
            'choices' => [
                1 => 'ch1',
                2 => 'ch2',
            ]
        ]);

        $vv1 = $f->viewValue(1);
        $vv2 = $f->viewValue(2);

        $this->assertSame('ch1', $vv1);
        $this->assertSame('ch2', $vv2);
    }
}
 