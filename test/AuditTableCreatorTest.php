<?php
namespace Cereblitz;
use Exception;

/**
 * Class AuditTableCreatorTest
 *
 *
 * @author Ed Cottrell <blitzmaster@cereblitz.com>
 * @copyright Copyright (c) 2015 Cereblitz LLC
 * @license MIT License
 * @link http://dev.mysql.com/doc/refman/5.7/en/create-trigger.html
 * @package Cereblitz
 */
class AuditTableCreatorTest extends \PHPUnit_Framework_TestCase
{
    /** @var \mysqli|\PDO|\object */
    public static $db;

    /** @var bool Preserve created tables and triggers for post-test inspection? */
    private static $preserveData = false;

    /**
     * Private method for checking that table exists
     * @param null $table
     * @return bool
     * @since Version 0.0.1
     */
    private function checkTableExists($table = null) {
        $result = self::$db->query("SHOW TABLES LIKE '$table'");
        $exists = (bool) $result->num_rows;
        $result->close();
        return $exists;
    }

    /**
     * This method is called when a test method did not execute successfully.
     *
     * @param Exception|\Throwable $e
     * @throws Exception|\Throwable
     */
    protected function onNotSuccessfulTest(Exception $e)
    {
        self::$preserveData = true;
        fwrite(STDOUT, __METHOD__ . "\n");
        throw $e;
    }

    /**
     * Test that the triggers all work correctly for a table with an AUTO_INCREMENT column
     */
    private function performTriggerTests_TableWithAutoIncrement()
    {
        self::$db->query("INSERT INTO `table_with_auto_increment` SET `foo` = 'Hello'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_auto_increment`");
        $row = $result->fetch_array();
        $this->assertEquals(1, $row[0]);

        self::$db->query("INSERT INTO `table_with_auto_increment` SET `foo` = 'World'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_auto_increment`");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        self::$db->query("UPDATE `table_with_auto_increment` SET `foo` = 'Goodbye' WHERE `foo` = 'Hello'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_auto_increment`");
        $row = $result->fetch_array();
        $this->assertEquals(3, $row[0]);

        self::$db->query("DELETE FROM `table_with_auto_increment` WHERE `foo` = 'Goodbye'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_auto_increment`");
        $row = $result->fetch_array();
        $this->assertEquals(4, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_auto_increment` WHERE `audit_event` = 'insert'");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_auto_increment` WHERE `audit_event` = 'update'");
        $row = $result->fetch_array();
        $this->assertEquals(1, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_auto_increment` WHERE `audit_event` = 'delete'");
        $row = $result->fetch_array();
        $this->assertEquals(1, $row[0]);
    }

    /**
     * Test that the triggers all work correctly for a table with a compound primary key
     */
    private function performTriggerTests_TableWithCompoundPrimary()
    {
        self::$db->query("INSERT INTO `table_with_compound_primary` SET `a` = 1, `b` = 40, `foo` = 'Hello'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(1, $row[0]);

        self::$db->query("INSERT INTO `table_with_compound_primary` SET `a` = 42, `b` = 17, `foo` = 'World'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        self::$db->query("UPDATE `table_with_compound_primary` SET `foo` = 'Goodbye' WHERE `foo` = 'Hello'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(3, $row[0]);

        self::$db->query("UPDATE `table_with_compound_primary` SET `a` = 42 WHERE `b` = 40");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(4, $row[0]);

        self::$db->query("DELETE FROM `table_with_compound_primary` WHERE `foo` = 'Goodbye'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(5, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary` WHERE `audit_event` = 'insert'");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary` WHERE `audit_event` = 'update'");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_compound_primary` WHERE `audit_event` = 'delete'");
        $row = $result->fetch_array();
        $this->assertEquals(1, $row[0]);
    }

    /**
     * Test that the triggers all work correctly for a table with no primary key
     */
    private function performTriggerTests_TableWithNoPrimary()
    {
        self::$db->query("INSERT INTO `table_with_no_primary` SET `a` = 1, `b` = 40");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(1, $row[0]);

        self::$db->query("INSERT INTO `table_with_no_primary` SET `a` = 42, `b` = 17");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        self::$db->query("UPDATE `table_with_no_primary` SET `b` = 52 WHERE `b` = 17");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(3, $row[0]);

        self::$db->query("UPDATE `table_with_no_primary` SET `a` = 42 WHERE `b` = 40");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(4, $row[0]);

        self::$db->query("DELETE FROM `table_with_no_primary` WHERE `a` = '42'");
        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary`");
        $row = $result->fetch_array();
        $this->assertEquals(6, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary` WHERE `audit_event` = 'insert'");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary` WHERE `audit_event` = 'update'");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);

        $result = self::$db->query("SELECT COUNT(*) FROM `audit_table_with_no_primary` WHERE `audit_event` = 'delete'");
        $row = $result->fetch_array();
        $this->assertEquals(2, $row[0]);
    }

    /**
     * Creates a connection to the test database
     * @throws Exception
     */
    public static function setUpBeforeClass()
    {
        self::$db = AuditTableCreatorDbTestConnection::connect();
    }

    /**
     * Drops any tables created by testing and destroys the connection to the test database
     */
    public static function tearDownAfterClass()
    {
        if (!self::$preserveData) {
            self::$db->query("DROP TABLE IF EXISTS
              `audit_table_with_auto_increment`,
              `table_with_auto_increment`,
              `table_with_compound_primary`,
              `audit_table_with_compound_primary`,
              `table_with_no_primary`,
              `audit_table_with_no_primary`,
              `placeholder`");
        }
        AuditTableCreatorDbTestConnection::disconnect();
        self::$db = null;
    }

    /**
     * Verify that an exception is thrown if the class is invoked on an invalid connection
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Query result does not have public method fetch or fetch_array
     */
    public function testGenerateSQLStatements_InvalidConnection_ResultHasNoFetchMethod()
    {
        self::$db->query('DROP TABLE IF EXISTS `placeholder`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `placeholder` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB");
        $stub = $this->getMockBuilder('\PDO')
            ->disableOriginalConstructor()
            ->getMock();
        $stub->method('query')
            ->willReturn(new \stdClass());
        $atc = new AuditTableCreator('placeholder', $stub);
        $atc->generateSQLStatements();
    }

    /**
     * Verify that an exception is thrown if the class is invoked on an invalid connection
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Connection does not have public method named query
     */
    public function testGenerateSQLStatements_InvalidConnection_Null()
    {
        $atc = new AuditTableCreator('nonexistent_table', null);
        $atc->generateSQLStatements();
    }

    /**
     * Verify that an exception is thrown if the class is invoked on a nonexistent table
     * @expectedException \UnexpectedValueException
     */
    public function testGenerateSQLStatements_NonexistentTable()
    {
        $atc = new AuditTableCreator('nonexistent_table', self::$db);
        $atc->generateSQLStatements();
    }

    /**
     * Test creation of the audit table and use of the triggers on an InnoDB table with an AUTO_INCREMENT column
     */
    public function testGenerateSQLStatements_CustomAuditTableName()
    {
        self::$db->query('DROP TABLE IF EXISTS `something_unusual`, `table_with_auto_increment`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_auto_increment` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_auto_increment'));

        $atc = new AuditTableCreator('table_with_auto_increment', self::$db, 'something_unusual');
        $atc->execute();

        $this->assertTrue($this->checkTableExists('something_unusual'));

        self::$db->query('DROP TABLE IF EXISTS `something_unusual`, `table_with_auto_increment`');
    }

    /**
     * Test creation of the audit table and use of the triggers on an InnoDB table with an AUTO_INCREMENT column
     */
    public function testGenerateSQLStatements_EnableLog()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_auto_increment`, `table_with_auto_increment`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_auto_increment` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_auto_increment'));

        $atc = new AuditTableCreator('table_with_auto_increment', self::$db);
        $atc->execute(__DIR__ . "/log.txt");

        $this->assertTrue($this->checkTableExists('audit_table_with_auto_increment'));
        $this->assertTrue(file_exists(__DIR__ . "/log.txt"));

        unlink(__DIR__ . "/log.txt");
    }

    /**
     * Test creation of the audit table and use of the triggers on an InnoDB table with an AUTO_INCREMENT column
     */
    public function testGenerateSQLStatements_InnoDbTableWithAutoIncrement()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_auto_increment`, `table_with_auto_increment`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_auto_increment` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_auto_increment'));

        $atc = new AuditTableCreator('table_with_auto_increment', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_auto_increment'));

        $this->performTriggerTests_TableWithAutoIncrement(); // runs additional tests to validate the triggers
    }

    /**
     * Test creation of the audit table and use of the triggers on a MyISAM table with an AUTO_INCREMENT column
     */
    public function testGenerateSQLStatements_MyISAMTableWithAutoIncrement()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_auto_increment`, `table_with_auto_increment`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_auto_increment` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=MyISAM");

        $this->assertTrue($this->checkTableExists('table_with_auto_increment'));

        $atc = new AuditTableCreator('table_with_auto_increment', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_auto_increment'));

        $this->performTriggerTests_TableWithAutoIncrement(); // runs additional tests to validate the triggers
    }

    /**
     * Test creation of the audit table and use of the triggers on an InnoDB table with a compound primary key
     */
    public function testGenerateSQLStatements_InnoDbTableWithCompoundPrimary()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_compound_primary`, `table_with_compound_primary`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_compound_primary` (
          `a` INT NOT NULL,
          `b` INT NOT NULL,
          `foo` CHAR(20),
          PRIMARY KEY (`a`, `b`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_compound_primary'));

        $atc = new AuditTableCreator('table_with_compound_primary', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_compound_primary'));

        $this->performTriggerTests_TableWithCompoundPrimary(); // runs additional tests to validate the triggers
    }

    /**
     * Test creation of the audit table and use of the triggers on a MyISAM table with a compound primary key
     */
    public function testGenerateSQLStatements_MyISAMTableWithCompoundPrimary()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_compound_primary`, `table_with_compound_primary`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_compound_primary` (
          `a` INT NOT NULL,
          `b` INT NOT NULL,
          `foo` CHAR(20),
          PRIMARY KEY (`a`, `b`)
          ) ENGINE=MyISAM");

        $this->assertTrue($this->checkTableExists('table_with_compound_primary'));

        $atc = new AuditTableCreator('table_with_compound_primary', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_compound_primary'));

        $this->performTriggerTests_TableWithCompoundPrimary(); // runs additional tests to validate the triggers
    }

    /**
     * Test creation of the audit table and use of the triggers on an InnoDB table with no primary key
     */
    public function testGenerateSQLStatements_InnoDbTableWithNoPrimary()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_no_primary`, `table_with_no_primary`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_no_primary` (
          `a` INT NOT NULL,
          `b` INT NOT NULL
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_no_primary'));

        $atc = new AuditTableCreator('table_with_no_primary', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_no_primary'));

        $this->performTriggerTests_TableWithNoPrimary(); // runs additional tests to validate the triggers
    }

    /**
     * Test creation of the audit table and use of the triggers on a MyISAM table with no primary key
     */
    public function testGenerateSQLStatements_MyISAMTableWithNoPrimary()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_no_primary`, `table_with_no_primary`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_no_primary` (
          `a` INT NOT NULL,
          `b` INT NOT NULL
          ) ENGINE=MyISAM");

        $this->assertTrue($this->checkTableExists('table_with_no_primary'));

        $atc = new AuditTableCreator('table_with_no_primary', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_no_primary'));

        $this->performTriggerTests_TableWithNoPrimary(); // runs additional tests to validate the triggers
    }

    /**
     * Test creation of the audit table and use of the triggers on an InnoDB table with an AUTO_INCREMENT column
     */
    public function testGenerateSQLStatements_PDO()
    {
        $pdo = new \PDO('mysql:dbname=' . AUDIT_TEST_DB_SCHEMA . ';host=' . AUDIT_TEST_DB_SERVER, AUDIT_TEST_DB_USER, AUDIT_TEST_DB_PASSWORD);
        $pdo->query('DROP TABLE IF EXISTS `audit_table_with_auto_increment`, `table_with_auto_increment`');
        $pdo->query("CREATE TABLE IF NOT EXISTS `table_with_auto_increment` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_auto_increment'));

        $atc = new AuditTableCreator('table_with_auto_increment', $pdo);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_auto_increment'));

        $this->performTriggerTests_TableWithAutoIncrement(); // runs additional tests to validate the triggers
    }

    /**
     * Test that SQL syntax errors trigger an exception
     *
     * @expectedException \Exception
     */
    public function testGenerateSQLStatements_TriggerSQLError_MySQLi()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_auto_increment`, `table_with_auto_increment`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_auto_increment` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_auto_increment'));

        $atc = new AuditTableCreator('table_with_auto_increment', self::$db, 'bad name`');
        $atc->execute(__DIR__ . "/log.txt");
        $this->assertTrue(file_exists(__DIR__ . "/log.txt"));

        unlink(__DIR__ . "/log.txt");
    }

    /**
     * Test that SQL syntax errors trigger an exception
     *
     * @expectedException \Exception
     */
    public function testGenerateSQLStatements_TriggerSQLError_PDO()
    {
        $pdo = new \PDO('mysql:dbname=' . AUDIT_TEST_DB_SCHEMA . ';host=' . AUDIT_TEST_DB_SERVER, AUDIT_TEST_DB_USER, AUDIT_TEST_DB_PASSWORD);
        $pdo->query('DROP TABLE IF EXISTS `audit_table_with_auto_increment`, `table_with_auto_increment`');
        $pdo->query("CREATE TABLE IF NOT EXISTS `table_with_auto_increment` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_auto_increment'));

        $atc = new AuditTableCreator('table_with_auto_increment', $pdo, 'bad name`');
        $atc->execute(__DIR__ . "/log.txt");
        $this->assertTrue(file_exists(__DIR__ . "/log.txt"));

        unlink(__DIR__ . "/log.txt");
    }
}
