<?php
require 'bootstrap.php';


class DjaTest extends PHPUnit_Framework_TestCase
{
    public function testGoodImport()
    {
        spl_autoload_register('import');
        $this->assertTrue(class_exists('\\Dja\\Auth\\Acl'));
    }

    public function testContollerShortcut()
    {
        $this->assertEquals('App\Controllers\IndexController::indexAction', controller('index/index'));
    }

    public function testContollerShortcutReverse()
    {
        $this->assertEquals('index/index', controller_reverse('App\Controllers\IndexController::indexAction'));
    }

    public function testExpr()
    {
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Expr', Expr('v'));
    }

    public function testWildcardParams()
    {
        $this->assertCount(0, wildcard('/'));
        $this->assertCount(1, wildcard('/index/'));
        $this->assertCount(2, wildcard('/param1/param12/'));
    }
}