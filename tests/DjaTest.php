<?php

class DjaTest extends PHPUnit_Framework_TestCase
{
    public function testGoodImport()
    {
        spl_autoload_register('import');
        $this->assertTrue(class_exists('\\Dja\\Db\\Model\\Model'));
    }

    public function testExpr()
    {
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Expr', Expr('v'));
    }
}