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
     * Create a pre-existing table and its audit table to test scenarios for existing tables and triggers
     */
    private function createPreexistingTable()
    {
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_existing_audit_table` (
          `id` INT NOT NULL AUTO_INCREMENT,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB");
        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        $atc->execute();
    }

    /**
     * Drop the tables generated by the test
     */
    private static function dropTestTables()
    {
        self::$db->query("DROP TABLE IF EXISTS
              `audit_table_with_auto_increment`,
              `table_with_auto_increment`,
              `audit_table_with_compound_primary`,
              `table_with_compound_primary`,
              `audit_table_with_no_primary`,
              `table_with_no_primary`,
              `placeholder`,
              `audit_table_with_unique_key`,
              `table_with_unique_key`,
              `audit_group_users`,
              `group_users`,
              `audit_groups`,
              `groups`,
              `audit_users`,
              `users`,
              `table_with_existing_audit_table`,
              `audit_table_with_existing_audit_table`");
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
        parent::onNotSuccessfulTest($e);
    }

    /**
     * Test that the triggers all work correctly for a table with an AUTO_INCREMENT column
     */
    private function performTriggerTests_TableWithAutoIncrement()
    {
        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_auto_increment` SET `foo` = 'Hello'");
        $this->assertEquals(1, $this->selectCount("audit_table_with_auto_increment"));

        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_auto_increment` SET `foo` = 'World'");
        $this->assertEquals(2, $this->selectCount("audit_table_with_auto_increment"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `table_with_auto_increment` SET `foo` = 'Goodbye' WHERE `foo` = 'Hello'");
        $this->assertEquals(3, $this->selectCount("audit_table_with_auto_increment"));

        /** @noinspection SqlResolve */
        self::$db->query("DELETE FROM `table_with_auto_increment` WHERE `foo` = 'Goodbye'");
        $this->assertEquals(4, $this->selectCount("audit_table_with_auto_increment"));

        $this->assertEquals(2, $this->selectCount("audit_table_with_auto_increment", "`audit_event` = 'insert'"));
        $this->assertEquals(1, $this->selectCount("audit_table_with_auto_increment", "`audit_event` = 'update'"));
        $this->assertEquals(1, $this->selectCount("audit_table_with_auto_increment", "`audit_event` = 'delete'"));
    }

    /**
     * Test that the triggers all work correctly for a table with a compound primary key
     */
    private function performTriggerTests_TableWithCompoundPrimary()
    {
        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_compound_primary` SET `a` = 1, `b` = 40, `foo` = 'Hello'");
        $this->assertEquals(1, $this->selectCount("audit_table_with_compound_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_compound_primary` SET `a` = 42, `b` = 17, `foo` = 'World'");
        $this->assertEquals(2, $this->selectCount("audit_table_with_compound_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `table_with_compound_primary` SET `foo` = 'Goodbye' WHERE `foo` = 'Hello'");
        $this->assertEquals(3, $this->selectCount("audit_table_with_compound_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `table_with_compound_primary` SET `a` = 42 WHERE `b` = 40");
        $this->assertEquals(4, $this->selectCount("audit_table_with_compound_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("DELETE FROM `table_with_compound_primary` WHERE `foo` = 'Goodbye'");
        $this->assertEquals(5, $this->selectCount("audit_table_with_compound_primary"));

        $this->assertEquals(2, $this->selectCount("audit_table_with_compound_primary", "`audit_event` = 'insert'"));
        $this->assertEquals(2, $this->selectCount("audit_table_with_compound_primary", "`audit_event` = 'update'"));
        $this->assertEquals(1, $this->selectCount("audit_table_with_compound_primary", "`audit_event` = 'delete'"));
    }

    /**
     * Test that the triggers all work correctly for a table with no primary key
     */
    private function performTriggerTests_TableWithNoPrimary()
    {
        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_no_primary` SET `a` = 1, `b` = 40");
        $this->assertEquals(1, $this->selectCount("audit_table_with_no_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_no_primary` SET `a` = 42, `b` = 17");
        $this->assertEquals(2, $this->selectCount("audit_table_with_no_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `table_with_no_primary` SET `b` = 52 WHERE `b` = 17");
        $this->assertEquals(3, $this->selectCount("audit_table_with_no_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `table_with_no_primary` SET `a` = 42 WHERE `b` = 40");
        $this->assertEquals(4, $this->selectCount("audit_table_with_no_primary"));

        /** @noinspection SqlResolve */
        self::$db->query("DELETE FROM `table_with_no_primary` WHERE `a` = '42'");
        $this->assertEquals(6, $this->selectCount("audit_table_with_no_primary"));

        $this->assertEquals(2, $this->selectCount("audit_table_with_no_primary", "`audit_event` = 'insert'"));
        $this->assertEquals(2, $this->selectCount("audit_table_with_no_primary", "`audit_event` = 'update'"));
        $this->assertEquals(2, $this->selectCount("audit_table_with_no_primary", "`audit_event` = 'delete'"));
    }

    /**
     * Test that the triggers all work correctly for a table with a UNIQUE KEY
     */
    private function performTriggerTests_TableWithUniqueKey()
    {
        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_unique_key` SET `foo` = 'Hello', `bar` = 'World'");
        $this->assertEquals(1, $this->selectCount("audit_table_with_unique_key"));

        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `table_with_unique_key` SET `foo` = 'Hello', `bar` = 'Montana'");
        $this->assertEquals(2, $this->selectCount("audit_table_with_unique_key"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `table_with_unique_key` SET `foo` = 'Goodbye' WHERE `foo` = 'Hello'");
        $this->assertEquals(4, $this->selectCount("audit_table_with_unique_key"));

        /** @noinspection SqlResolve */
        self::$db->query("DELETE FROM `table_with_unique_key` WHERE `foo` = 'Goodbye'");
        $this->assertEquals(6, $this->selectCount("audit_table_with_unique_key"));

        $this->assertEquals(2, $this->selectCount("audit_table_with_unique_key", "`audit_event` = 'insert'"));
        $this->assertEquals(2, $this->selectCount("audit_table_with_unique_key", "`audit_event` = 'update'"));
        $this->assertEquals(2, $this->selectCount("audit_table_with_unique_key", "`audit_event` = 'delete'"));
        $this->assertGreaterThanOrEqual(2, $this->selectCount("audit_table_with_unique_key", "`foo` = 'Goodbye' AND `bar` = 'World'"));
    }

    /**
     * Internal method for counting number of rows in a table, with an optional condition
     *
     * @param string $table
     * @param string|null $whereClause
     * @returns int
     */
    private function selectCount($table, $whereClause = null)
    {
        /** @noinspection SqlResolve */
        $sql = "SELECT COUNT(*) FROM `$table`";
        if (null !== $whereClause) {
            $sql .= " WHERE $whereClause";
        }
        $result = self::$db->query($sql);
        $row = $result->fetch_array();
        return $row[0];
    }

    /**
     * Creates a connection to the test database
     * @throws Exception
     */
    public static function setUpBeforeClass()
    {
        self::$db = AuditTableCreatorDbTestConnection::connect();
        self::dropTestTables();
    }

    /**
     * Drops any tables created by testing and destroys the connection to the test database
     */
    public static function tearDownAfterClass()
    {
        if (!self::$preserveData) {
            self::dropTestTables();
        }
        AuditTableCreatorDbTestConnection::disconnect();
        self::$db = null;
    }

    /**
     * Test that no exception is thrown if the audit table already exists and an exception should not be thrown
     */
    public function testCheckTableExists_AuditTableExists_DoNotThrowException()
    {
        $this->dropTestTables();
        $this->createPreexistingTable();
        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        $sql = $atc->generateSQLStatements();
        $this->assertNotEmpty(preg_grep('/CREATE TABLE .*`audit_table_with_existing_audit_table`/', $sql));
        $this->assertEmpty(preg_grep('/ALTER TABLE /', $sql));
    }

    /**
     * Test that an exception is thrown if the audit table already exists and an exception should be thrown
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Audit table already exists
     */
    public function testCheckTableExists_AuditTableExists_ThrowException()
    {
        $this->dropTestTables();
        $this->createPreexistingTable();
        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        $atc->setThrowErrorIfAuditTableExists(true);
        $atc->generateSQLStatements();
    }

    /**
     * Test that an exception is thrown if the base table does not exist
     *
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Base table foo does not exist
     */
    public function testCheckTableExists_BaseTableDoesNotExist()
    {
        $atc = new AuditTableCreator('foo', self::$db);
        $atc->generateSQLStatements();
    }

    /**
     * Test that no exception is thrown if the audit triggers error is enabled, but the triggers don't exist
     */
    public function testCheckWhetherTriggersExist_TriggersDoNotExist()
    {
        $this->dropTestTables();
        $this->createPreexistingTable();
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_deletes");
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_inserts");
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_updates");

        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        $atc->setThrowErrorIfTriggersExist(true);
        $sql = $atc->generateSQLStatements();
        $this->assertNotEmpty(preg_grep('/CREATE TRIGGER/', $sql));
    }

    /**
     * Test that no exception is thrown if the audit triggers already exist and no exception should be thrown
     */
    public function testCheckWhetherTriggersExist_TriggersExist_DoNotThrowException()
    {
        $this->dropTestTables();
        $this->createPreexistingTable();
        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        $sql = $atc->generateSQLStatements();
        $this->assertEmpty(preg_grep('/CREATE TRIGGER/', $sql));
    }

    /**
     * Test that an exception is thrown if the delete audit trigger already exists and an exception should be thrown
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Audit trigger for deletions on table table_with_existing_audit_table already exists
     */
    public function testCheckWhetherTriggersExist_TriggersExist_ThrowException_Delete()
    {
        $this->dropTestTables();
        $this->createPreexistingTable();
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_inserts");
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_updates");

        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        $atc->setThrowErrorIfTriggersExist(true);
        $atc->generateSQLStatements();
    }

    /**
     * Test that an exception is thrown if the insert audit trigger already exists and an exception should be thrown
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Audit trigger for insertions on table table_with_existing_audit_table already exists
     */
    public function testCheckWhetherTriggersExist_TriggersExist_ThrowException_Insert()
    {
        $this->dropTestTables();
        $this->createPreexistingTable();
        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_deletes");
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_updates");

        $atc->setThrowErrorIfTriggersExist(true);
        $atc->generateSQLStatements();
    }

    /**
     * Test that an exception is thrown if the insert audit trigger already exists and an exception should be thrown
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Audit trigger for updates on table table_with_existing_audit_table already exists
     */
    public function testCheckWhetherTriggersExist_TriggersExist_ThrowException_Update()
    {
        $this->dropTestTables();
        $this->createPreexistingTable();
        $atc = new AuditTableCreator('table_with_existing_audit_table', self::$db);
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_deletes");
        self::$db->query("DROP TRIGGER IF EXISTS `audit_table_with_existing_audit_table_inserts");

        $atc->setThrowErrorIfTriggersExist(true);
        $atc->generateSQLStatements();
    }

    /**
     * Verify that everything works in a complex situation (tables with and without AUTO_INCREMENT,
     * with and without UNIQUE KEYs, with and without FOREIGN KEY relations, etc.)
     */
    public function testGenerateSQLStatements_ComplexExample()
    {
        self::$db->query("CREATE TABLE IF NOT EXISTS `users` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '',
              `name` VARCHAR(45) NOT NULL COMMENT '',
              `created` DATETIME NOT NULL COMMENT '',
              PRIMARY KEY (`id`)  COMMENT '',
              INDEX `name` (`name` ASC)  COMMENT '',
              INDEX `created` (`created` ASC)  COMMENT '')
            ENGINE = InnoDB");
        /** @noinspection SqlResolve */
        self::$db->query("CREATE TABLE IF NOT EXISTS `groups` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '',
              `owner_user` INT UNSIGNED NOT NULL COMMENT '',
              `created` DATETIME NOT NULL COMMENT '',
              PRIMARY KEY (`id`)  COMMENT '',
              INDEX `owner_user` (`owner_user` ASC)  COMMENT '',
              INDEX `created` (`created` ASC)  COMMENT '',
              CONSTRAINT `groups_owner_user`
                FOREIGN KEY (`owner_user`)
                REFERENCES `users` (`id`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION)
            ENGINE = InnoDB");
        /** @noinspection SqlResolve */
        self::$db->query("CREATE TABLE IF NOT EXISTS `group_users` (
              `group_id` INT UNSIGNED NOT NULL COMMENT '',
              `user_id` INT UNSIGNED NOT NULL COMMENT '',
              `joined` DATETIME NOT NULL COMMENT '',
              UNIQUE INDEX `group_user` (`group_id` ASC, `user_id` ASC)  COMMENT '',
              INDEX `group_users_user_id_idx` (`user_id` ASC)  COMMENT '',
              CONSTRAINT `group_users_group_id`
                FOREIGN KEY (`group_id`)
                REFERENCES `groups` (`id`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION,
              CONSTRAINT `group_users_user_id`
                FOREIGN KEY (`user_id`)
                REFERENCES `users` (`id`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION)
            ENGINE = InnoDB");


        $this->assertTrue($this->checkTableExists('users'));
        $this->assertTrue($this->checkTableExists('groups'));
        $this->assertTrue($this->checkTableExists('group_users'));

        $atc = new AuditTableCreator('users', self::$db);
        $atc->execute();
        $atc = new AuditTableCreator('groups', self::$db);
        $atc->execute();
        $atc = new AuditTableCreator('group_users', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_users'));
        $this->assertTrue($this->checkTableExists('audit_groups'));
        $this->assertTrue($this->checkTableExists('audit_group_users'));

        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `users` (`name`, `created`)
          VALUES
            ('Joe', '1999-10-31 19:00:00'),
            ('Bob', '2003-08-12 10:09:17'),
            ('Susan', '2007-04-06 05:45:39')");
        $this->assertEquals(3, $this->selectCount("users"));
        $this->assertEquals(3, $this->selectCount("audit_users"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `users` SET `name` = 'Joseph' WHERE `name` = 'Joe'");
        $this->assertEquals(3, $this->selectCount("users"));
        $this->assertEquals(4, $this->selectCount("audit_users"));

        /** @noinspection SqlResolve */
        self::$db->query("UPDATE `users` SET `name` = 'Robert' WHERE `name` = 'Bob'");
        $this->assertEquals(3, $this->selectCount("users"));
        $this->assertEquals(5, $this->selectCount("audit_users"));

        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `groups` (`owner_user`, `created`)
           VALUES
             (1, '2014-01-01 00:00:00'),
             (1, '2014-07-04 17:07:06'),
             (2, '2015-02-14 03:06:09')");
        $this->assertEquals(3, $this->selectCount("groups"));
        $this->assertEquals(3, $this->selectCount("audit_groups"));

        /** @noinspection SqlResolve */
        self::$db->query("INSERT INTO `group_users` (`group_id`, `user_id`, `joined`)
           VALUES
             (1, 1, '2014-01-01 00:00:00'),
             (1, 2, '2014-01-01 00:00:00'),
             (1, 3, '2014-01-01 00:00:00'),
             (2, 1, '2014-07-04 17:07:06'),
             (2, 2, '2014-07-04 17:07:06'),
             (3, 2, '2015-02-14 03:06:09')");
        $this->assertEquals(6, $this->selectCount("group_users"));
        $this->assertEquals(6, $this->selectCount("audit_group_users"));

        /** @noinspection SqlResolve */
        self::$db->query("DELETE FROM `users` WHERE `name` = 'Susan'");
        $this->assertEquals(3, $this->selectCount("users"), "Oops! Can't delete a user who is a member of a group.");
        $this->assertEquals(5, $this->selectCount("audit_users"), "Oops! Can't delete a user who is a member of a group.");

        /** @noinspection SqlResolve */
        self::$db->query("DELETE FROM `group_users` WHERE `user_id` = 3");
        $this->assertEquals(5, $this->selectCount("group_users"));
        $this->assertEquals(7, $this->selectCount("audit_group_users"));

        /** @noinspection SqlResolve */
        self::$db->query("DELETE FROM `users` WHERE `name` = 'Susan'");
        $this->assertEquals(2, $this->selectCount("users"), "Got it this time.");
        $this->assertEquals(6, $this->selectCount("audit_users"), "Got it this time.");
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
    public function testGenerateSQLStatements_WithAutoIncrement_InnoDbTable()
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
    public function testGenerateSQLStatements_WithAutoIncrement_MyISAMTable()
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
    public function testGenerateSQLStatements_WithCompoundPrimary_InnoDbTable()
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
    public function testGenerateSQLStatements_WithCompoundPrimary_MyISAMTable()
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
    public function testGenerateSQLStatements_WithNoPrimary_InnoDbTable()
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
    public function testGenerateSQLStatements_WithNoPrimary_MyISAMTable()
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
     * Test creation of the audit table and use of the triggers on a MyISAM table with a UNIQUE KEY
     */
    public function testGenerateSQLStatements_WithUniqueKey_MyISAMTable()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_unique_key`, `table_with_unique_key`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_unique_key` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          `bar` CHAR(20),
          PRIMARY KEY (`id`),
          UNIQUE KEY `fubar` (`foo`, `bar`)
          ) ENGINE=MyISAM");

        $this->assertTrue($this->checkTableExists('table_with_unique_key'));

        $atc = new AuditTableCreator('table_with_unique_key', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_unique_key'));

        $result = self::$db->query("SHOW CREATE TABLE `audit_table_with_unique_key`");
        $row = $result->fetch_array();
        $this->assertRegExp("/\bKEY `fubar`/", $row[1]);
        $this->assertNotRegExp("/\bUNIQUE\b/", $row[1]);
        $result->close();

        $this->performTriggerTests_TableWithUniqueKey(); // runs additional tests to validate the triggers
    }

    /**
     * Test creation of the audit table and use of the triggers on an InnoDB table with a UNIQUE KEY
     */
    public function testGenerateSQLStatements_WithUniqueKey_InnoDBTable()
    {
        self::$db->query('DROP TABLE IF EXISTS `audit_table_with_unique_key`, `table_with_unique_key`');
        self::$db->query("CREATE TABLE IF NOT EXISTS `table_with_unique_key` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `foo` CHAR(20),
          `bar` CHAR(20),
          PRIMARY KEY (`id`),
          UNIQUE KEY `fubar` (`foo`, `bar`)
          ) ENGINE=InnoDB");

        $this->assertTrue($this->checkTableExists('table_with_unique_key'));

        $atc = new AuditTableCreator('table_with_unique_key', self::$db);
        $atc->execute();

        $this->assertTrue($this->checkTableExists('audit_table_with_unique_key'));

        $result = self::$db->query("SHOW CREATE TABLE `audit_table_with_unique_key`");
        $row = $result->fetch_array();
        $this->assertRegExp("/\bKEY `fubar`/", $row[1]);
        $this->assertNotRegExp("/\bUNIQUE\b/", $row[1]);
        $result->close();

        $this->performTriggerTests_TableWithUniqueKey(); // runs additional tests to validate the triggers
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
