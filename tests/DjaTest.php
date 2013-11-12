<?php

class DjaTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected static $db;

    public function testGoodImport()
    {
        spl_autoload_register('import');
        $this->assertTrue(class_exists('\\Dja\\Db\\Model\\Model'));
    }

    public function testExpr()
    {
        $this->assertInstanceOf('\\Dja\\Db\\Model\\Expr', Expr('v'));
    }

    public function testDb1()
    {
        $result = self::$db->fetchAssoc('SELECT * FROM "user" LIMIT 1');
        $this->assertArrayHasKey('user_id', $result);

        $q = self::$db->createQueryBuilder();
        /** @var \Doctrine\DBAL\Driver\Statement $stmt */
        $stmt = $q->select('*')->from('"user"', 't')->setMaxResults(50)->execute();
        $result = $stmt->rowCount();
        $this->assertEquals(50, $result);
    }

    public static function setUpBeforeClass()
    {
        self::$db = Dja\Db\Model\Metadata::getDefaultDbConnection();
    }

    public static function tearDownAfterClass()
    {

    }
}
