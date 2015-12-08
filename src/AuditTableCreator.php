<?php

namespace Cereblitz;

/**
 * Class AuditTableCreator
 * For a given table, creates an audit table that tracks all versions of each
 * row of the data:
 *   (1) as it existed when the INSERT command was executed,
 *   (2) as it exists after each UPDATE, and
 *   (3) as it existed immediately before a DELETE command.
 * For each such change, the audit table will contain:
 *   (1) a unique id, audit_id;
 *   (2) a timestamp, audit_datetime;
 *   (3) an event name (one of 'insert', 'update', or 'delete');
 *   (4) a row-specific version number; and
 *   (5) a copy of all data from the row in the original table (or, for an
 *          INSERT, the data that is being inserted).
 * Parameters are a table name, a database connection, and an optional name
 * for the audit table (default is audit_ORIGINAL_TABLE_NAME)
 *
 * This class uses triggers to log events. In versions of MySQL prior to 5.7, you can
 * create only one trigger per event type per table. So, this class will not work for you
 * if you need to use MySQL versions before 5.7 and also need non-audit-log triggers on
 * the table to be audited. Further, creating an audit table
 * with this class requires appropriate privileges (TRIGGER and possibly SUPER). Per
 * <a href="http://dev.mysql.com/doc/refman/5.7/en/create-trigger.html">the manual</a>,
 *
 * <blockquote>CREATE TRIGGER requires the TRIGGER privilege for the table associated
 * with the trigger. The statement might also require the SUPER privilege, depending
 * on the DEFINER value, as described later in this section. If binary logging is
 * enabled, CREATE TRIGGER might require the SUPER privilege, as described in Section
 * 19.7, “Binary Logging of Stored Programs”.</blockquote>
 *
 * Note: this does not currently support foreign keys or other constraints;
 * constraints are simply ignored.
 *
 * Note: this does not support logging of rows affected by DROP TABLE or TRUNCATE TABLE,
 * because those actions do not activate the DELETE trigger. Audit entries for those events
 * should be manually created if desired.
 *
 * Note: if a primary key is present, the audit table will contain a versioned history of
 * the data in the original table. Without a primary key, no version history is possible.
 *
 * @author Ed Cottrell <blitzmaster@cereblitz.com>
 * @copyright Copyright (c) 2015 Cereblitz LLC
 * @license MIT License
 * @link http://dev.mysql.com/doc/refman/5.7/en/create-trigger.html
 * @package Cereblitz Audit Table Creator
 *
 * @todo Add optional support for foreign key constraints
 */
class AuditTableCreator
{
    /** @var bool */
    private $auditDeleteTriggerExists = false;

    /** @var bool */
    private $auditInsertTriggerExists = false;

    /** @var bool */
    private $auditUpdateTriggerExists = false;

    /** @var bool */
    private $auditTableExists = false;

    /** @var string */
    private $auditTableName = null;

    /** @var string|null */
    private $autoIncrementColumn = null;

    /** @var \mysqli|\PDO|object */
    private $conn = null;

    /** @var string|null The name of the fetch or fetch_array method */
    private $fetchMethod = null;

    /** @var string */
    private $mainTableCreateStatement = null;

    /** @var string[] An array of the names (without definitions) of the columns in the table to be audited */
    private $mainTableColumnNames = array();

    /** @var string|null */
    private $primaryKeyDefinition = null;

    /** @var string[] An array of the SQL statements required to fully set up the audit table */
    private $sqlStatements = array();

    /** @var string */
    private $table = null;

    /** @var bool Throw an error if the audit table exists? By default, this is false, and the CREATE TABLE statement
     * uses IF NOT EXISTS
     */
    private $throwErrorIfAuditTableExists = false;

    /** @var bool Throw an error if the audit table exists? By default, this is false, and the CREATE TABLE statement
     * uses IF NOT EXISTS
     */
    private $throwErrorIfTriggersExist = false;

    /** @var string[]|null */
    private $uniqueKeys = null;


    /**
     * @param string $table The table being audited
     * @param \mysqli|\PDO|object $conn The connection to use (can be, e.g., a Cereblitz\DatabaseInterface, a mysqli,
     *                      PDO, or any other object implementing a method "query" by which a query can be run directly
     *                      on the database). It also needs to return a result object that implements either "fetch"
     *                      (PDO-style) or "fetch_array" (MySQLi-style).
     * @param string $auditTableName The default is audit_ORIGINAL_TABLE_NAME
     * @since Version 0.0.1
     */
    public function __construct($table, $conn, $auditTableName = null)
    {
        // Make sure the connection is valid
        if (!method_exists($conn, 'query')) {
            throw new \BadMethodCallException('Connection does not have public method named query');
        }

        // @codeCoverageIgnoreStart
        $this->auditTableName = $auditTableName ?: 'audit_' . $table;
        $this->conn = $conn;
        $this->table = $table;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Add the id, datetime, event, and version columns to the audit table, as well as appropriate keys.
     * This (1) turns any AUTO_INCREMENT columns into normal INTs (or whatever kind of integer value),
     * (2) replaces the primary key (if any), (3) adds the audit-specific columns, and (4) adds a regular
     * key corresponding to the audited table's primary key (if any).
     *
     * @returns string|null The SQL for the ALTER TABLE statement or null if no ALTER TABLE statement is needed
     * @since Version 0.0.1
     */
    private function addAuditTableColumnsAndKeys()
    {
        if ($this->auditTableExists) {
            return null;
        }
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "ALTER TABLE `{$this->auditTableName}`\n";
        if ($this->autoIncrementColumn) {
            $autoIncrementColumnName = preg_replace('/^\s*`([^`]+)`.*/', '$1', $this->autoIncrementColumn);
            $formerAutoIncrementColumnDefinitionWithoutAI = preg_replace('/\s+AUTO_INCREMENT/i', '', $this->autoIncrementColumn);
            $sql .= "    CHANGE COLUMN `$autoIncrementColumnName` $formerAutoIncrementColumnDefinitionWithoutAI,\n";
        }
        if ($this->primaryKeyDefinition) {
            $sql .= "    DROP PRIMARY KEY,\n";
        }
        $sql .= "    ADD COLUMN `audit_id` INT AUTO_INCREMENT NOT NULL FIRST,
    ADD PRIMARY KEY (`audit_id`),
    ADD COLUMN `audit_datetime` DATETIME NOT NULL AFTER `audit_id`,
    ADD COLUMN `audit_event` CHAR(7) NOT NULL DEFAULT 'insert' AFTER `audit_datetime`";
        if ($this->primaryKeyDefinition) {
            $sql .= ",
    ADD COLUMN `audit_item_version` INT NULL AFTER `audit_event`,
    ADD KEY `real_primary_key` ({$this->primaryKeyDefinition})";
        }
        foreach ($this->uniqueKeys as $uniqueKey) {
            $sql .= ",
    DROP " . preg_replace("/(KEY\s*`[^`]+`).+/", "\$1", $uniqueKey) . ",
    ADD $uniqueKey";
        }

        $this->sqlStatements[] = $sql;
        return $sql;
    }

    /**
     * Check that the base table exists. We can't very well audit it if it doesn't!
     * @return bool True if success; throws an error otherwise
     * @throws \Exception
     * @since Version 0.0.1
     */
    private function checkTableExists()
    {
        // Make sure the table exists
        $baseTableExists = false;
        $result = $this->conn->query('SHOW TABLES'); // don't use the actual table name, just in case there's some sort of weird injection issue
        $row = call_user_func(array($result, $this->fetchMethod));
        while ($row) {
            if ($row[0] === $this->table) {
                $baseTableExists = true;
            } elseif ($row[0] === $this->auditTableName) {
                $this->auditTableExists = true;
            }
            if ($baseTableExists && $this->auditTableExists) {
                break;
            }
            $row = call_user_func(array($result, $this->fetchMethod));
        }
        if (!$baseTableExists) {
            throw new \UnexpectedValueException('Base table does not exist');
        }
        if ($this->auditTableExists && $this->throwErrorIfAuditTableExists) {
            throw new \Exception('Audit table already exists');
        }
        return true;
    }

    /**
     * Check whether the triggers exist.
     *
     * @throws \Exception
     */
    private function checkWhetherTriggersExist()
    {
        $result = $this->conn->query("SHOW TRIGGERS");
        $row = call_user_func(array($result, $this->fetchMethod));
        while ($row) {
            $trigger = $row[0];
            if ("audit_{$this->table}_deletes" === $trigger) {
                $this->auditDeleteTriggerExists = true;
            } elseif ("audit_{$this->table}_inserts" === $trigger) {
                $this->auditInsertTriggerExists = true;
            } elseif ("audit_{$this->table}_updates" === $trigger) {
                $this->auditUpdateTriggerExists = true;
            }
            $row = call_user_func(array($result, $this->fetchMethod));
        }
        if ($this->throwErrorIfTriggersExist) {
            if ($this->auditDeleteTriggerExists) {
                throw new \Exception("Audit trigger for deletions on table {$this->table} already exists");
            }
            if ($this->auditInsertTriggerExists) {
                throw new \Exception("Audit trigger for insertions on table {$this->table} already exists");
            }
            if ($this->auditUpdateTriggerExists) {
                throw new \Exception("Audit trigger for updates on table {$this->table} already exists");
            }
        }
    }

    /**
     * Generate the SQL to create the audit table and return it.
     *
     * @return string The CREATE TABLE statement for the audit table
     * @since Version 0.0.1
     */
    private function createAuditTable()
    {
        /** @noinspection SqlNoDataSourceInspection */
        $ifNotExistsClause = $this->throwErrorIfAuditTableExists ? "" : "IF NOT EXISTS ";
        $sql = "CREATE TABLE $ifNotExistsClause`{$this->auditTableName}` LIKE `{$this->table}`";
        $this->sqlStatements[] = $sql;
        return $sql;
    }

    /**
     * Generate the SQL for the DELETE trigger
     *
     * @return string The SQL to create the trigger
     * @since Version 0.0.1
     */
    private function createDeleteTrigger()
    {
        $subqueryWhere = $this->getSubqueryWhereConditionForAuditTableInsertion("OLD");
        $fields = $this->getFieldsForAuditTableInsert("OLD");
        $sql = "CREATE TRIGGER `audit_{$this->table}_deletes` BEFORE DELETE ON `{$this->table}`
    FOR EACH ROW
    BEGIN
        INSERT INTO `{$this->auditTableName}`
        SET `audit_datetime` = NOW(),
            `audit_event` = 'delete'," .
            ($this->primaryKeyDefinition
                ? "`audit_item_version` = IFNULL(
                (
                    SELECT MAX(`audit_item_version`) + 1
                    FROM `{$this->auditTableName}` `source`
                    WHERE $subqueryWhere
                ),
                1),"
                : "") . "
            $fields;
    END";
        $this->sqlStatements[] = $sql;
        return $sql;
    }

    /**
     * Generate the SQL for the INSERT trigger
     *
     * @return string The SQL to create the trigger
     * @since Version 0.0.1
     */
    private function createInsertTrigger()
    {
        $subqueryWhere = $this->getSubqueryWhereConditionForAuditTableInsertion("NEW");
        $fields = $this->getFieldsForAuditTableInsert("NEW");
        $sql = "CREATE TRIGGER `audit_{$this->table}_inserts` AFTER INSERT ON `{$this->table}`
    FOR EACH ROW
    BEGIN
        INSERT INTO `{$this->auditTableName}`
        SET `audit_datetime` = NOW(),
            `audit_event` = 'insert'," .
            ($this->primaryKeyDefinition
                ? "`audit_item_version` = IFNULL(
                                (
                                    SELECT MAX(`audit_item_version`) + 1
                                    FROM `{$this->auditTableName}` `source`
                                    WHERE $subqueryWhere
                                ),
                                1),"
                : "") . "
            $fields;
    END";
        $this->sqlStatements[] = $sql;
        return $sql;
    }

    /**
     * Generate the SQL for the UPDATE trigger
     *
     * @return string The SQL to create the trigger
     * @since Version 0.0.1
     */
    private function createUpdateTrigger()
    {
        $subqueryWhere = $this->getSubqueryWhereConditionForAuditTableInsertion("OLD");
        $fields = $this->getFieldsForAuditTableInsert("NEW");
        $sql = "CREATE TRIGGER `audit_{$this->table}_updates` BEFORE UPDATE ON `{$this->table}`
    FOR EACH ROW
    BEGIN
        INSERT INTO `{$this->auditTableName}`
        SET `audit_datetime` = NOW(),
            `audit_event` = 'update'," .
            ($this->primaryKeyDefinition
                ? "`audit_item_version` = IFNULL(
                                (
                                    SELECT MAX(`audit_item_version`) + 1
                                    FROM `{$this->auditTableName}` `source`
                                    WHERE $subqueryWhere
                                ),
                                1),"
                : "") . "
            $fields;
    END";
        $this->sqlStatements[] = $sql;
        return $sql;
    }

    /**
     * Figure out which method for the connection (fetch or fetch_array) can be used to
     * directly execute a query.
     *
     * @returns string The name of the method
     * @since Version 0.0.1
     */
    private function determineFetchMethod()
    {
        $result = $this->conn->query("SELECT 1");
        if (method_exists($result, 'fetch')) { // PDO-style
            $this->fetchMethod = 'fetch';
        } elseif (method_exists($result, 'fetch_array')) { // MySQLi-style
            $this->fetchMethod = 'fetch_array';
        } else {
            throw new \BadMethodCallException('Query result does not have public method fetch or fetch_array');
        }
        return $this->fetchMethod;
    }

    /**
     * Actually execute the creation process
     *
     * @param string|null $logFile Optional; a path to a file to which the commands and their results should be logged
     * @return bool True on success. (Throws \Exception on error
     * @throws \Exception
     * @since Version 0.0.1
     */
    public function execute($logFile = null)
    {
        $this->generateSQLStatements();
        $file = null;

        if (!empty($logFile)) {
            $logFile = preg_replace('~\\\\~', DIRECTORY_SEPARATOR, $logFile);
            if (preg_match('/^(?!.+\.\.)(\/[a-z0-9_.]+)+$/i', $logFile)) {
                $file = fopen($logFile, 'a');
            }
        }

        foreach ($this->sqlStatements as $sql) {
            $this->executeOneStatement($sql, $file);
        }
        return true;
    }

    /**
     * Execute a single statement from the generated statements
     *
     * @param string $sql The statement
     * @param null|\resource $file A handle to a log file
     * @throws \Exception
     */
    private function executeOneStatement($sql, $file = null)
    {
        $errorDetails = null;
        if (isset($file)) {
            fwrite($file, "Executing SQL:\n$sql\n");
        }
        $result = $this->conn->query($sql);
        if (!empty($this->conn->errno)) {
            $errorDetails = "Error in MySQL Query:\n" .
                "Error Number: {$this->conn->errno}\n" .
                "Error Message: {$this->conn->error}\n" .
                "SQL Statement:\n    $sql";
        } elseif (false === $result && method_exists($this->conn, 'errorInfo')) {
            $errorInfo = $this->conn->errorInfo();
            $errorDetails = "Error in MySQL Query:\n" .
                "SQL State: {$errorInfo[0]}\n" .
                "Error Number: {$errorInfo[1]}\n" .
                "Error Message: {$errorInfo[2]}\n" .
                "SQL Statement:\n    $sql";
        }
        if (null !== $errorDetails) {
            if (isset($file)) {
                fwrite($file, $errorDetails . "\n");
            }
            throw new \Exception($errorDetails);
        }
    }

    /**
     * Get the fields for insertion into the audit table. Produces a list, something like this:
     *
     *    id = NEW.id,
     *    my_data = NEW.my_data,
     *    `timestamp` = NEW.`timestamp`
     *
     * @param $source string One of "NEW" or "OLD"
     * @returns string
     * @since Version 0.0.1
     */
    private function getFieldsForAuditTableInsert($source = "NEW")
    {
        $fields = '';
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($this->mainTableColumnNames as $column) {
            $fields .= "`$column` = " . $source . ".`$column`,";
        }
        $fields = substr($fields, 0, -1);
        return $fields;
    }

    /**
     * Get the fields for use in the WHERE clause of the subquery when doing an INSERT
     * into the audit table. Produces a boolean condition, something like this:
     *
     *      `source`.id = OLD.`id`
     *
     * @param $source string One of "NEW" or "OLD"
     * @returns string
     * @since Version 0.0.1
     */
    private function getSubqueryWhereConditionForAuditTableInsertion($source = "NEW")
    {
        $condition = '';

        $primaryKeyFields = preg_split('/(?:\(\d+\))?[, ]+/', $this->primaryKeyDefinition);

        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($primaryKeyFields as $field) {
            $condition .= "$field = " . $source . ".$field AND ";
        }
        $condition = substr($condition, 0, -5);
        return $condition;
    }

    /**
     * Generate the SQL statements to create the audit table and set it up
     *
     * @returns string[] An array of the SQL statements
     * @since Version 0.0.1
     */
    public function generateSQLStatements()
    {
        // need this before continuing - otherwise, we can't fetch results
        $this->determineFetchMethod();

        // make sure the base table exists and see if the audit table exists
        $this->checkTableExists();

        // see if any of the triggers exist
        $this->checkWhetherTriggersExist();

        // get the base table CREATE statement and parse important pieces
        $this->getMainTableCreateStatement();
        $this->getMainTableAutoIncrementColumn();
        $this->getMainTablePrimaryKey();
        $this->getMainTableColumnNames();
        $this->getMainTableUniqueKeys();

        // create the audit table and tweak it as needed
        $this->createAuditTable();
        $this->addAuditTableColumnsAndKeys();

        // set up the triggers to do the logging of changes
        if (!$this->auditInsertTriggerExists) {
            $this->createInsertTrigger();
        }
        if (!$this->auditDeleteTriggerExists) {
            $this->createDeleteTrigger();
        }
        if (!$this->auditUpdateTriggerExists) {
            $this->createUpdateTrigger();
        }

        return $this->sqlStatements;
    }

    /**
     * Get the CREATE TABLE statement for the table being audited.
     *
     * @return string
     * @since Version 0.0.1
     */
    private function getMainTableCreateStatement()
    {
        $sql = "SHOW CREATE TABLE `{$this->table}`";
        $result = $this->conn->query($sql);
        $row = call_user_func(array($result, $this->fetchMethod));
        $this->mainTableCreateStatement = $row[1];
        return $this->mainTableCreateStatement;
    }

    /**
     * Get the definition of any AUTO_INCREMENT column in the table to be audited.
     * Will return a string like "`id` INT(11) NOT NULL AUTO_INCREMENT" (any leading or trailing spaces or commas
     * stripped)
     *
     * @returns string|null
     * @since Version 0.0.1
     */
    private function getMainTableAutoIncrementColumn()
    {
        preg_match("~^(?!.+\bENGINE\b)\s*(.+? AUTO_INCREMENT.*?),?$~m", $this->mainTableCreateStatement, $matches);
        if (!empty($matches)) {
            $this->autoIncrementColumn = $matches[1];
        }
        return $this->autoIncrementColumn;
    }

    /**
     * Get the names (just the names, no definitions or backticks) of the columns in the table to be audited.
     * Will return an array of strings like array('id', 'first_name', 'last_name', 'balance', ...)
     *
     * @returns string[]
     * @since Version 0.0.1
     */
    private function getMainTableColumnNames()
    {
        preg_match_all("~^\s*`\K([^`]+)(?:`.*,?$)~m", $this->mainTableCreateStatement, $matches);
        if (!empty($matches)) {
            $this->mainTableColumnNames = $matches[1];
        }
        return $this->mainTableColumnNames;
    }

    /**
     * Get the definition of the primary key, if any, in the table to be audited. Columns retain
     * any backtick-quoting (e.g., the returned value might be "`foo`, `bar`").
     * Given a string like this:
     *     CREATE TABLE `foo` (
     *         `bar` INT AUTO_INCREMENT NOT NULL,
     *         `baz` CHAR(10),
     *         PRIMARY KEY (`bar`),
     *         KEY `something` (`baz`)
     *     ) ENGINE=InnoDB
     * this method will return just the part between "PRIMARY KEY (" and the closing ")",
     * i.e., "`bar`".
     *
     * @returns string|null
     * @since Version 0.0.1
     */
    private function getMainTablePrimaryKey()
    {
        preg_match("~^\s*PRIMARY KEY\s*(?:`[^`]+`\s*)?\((.+?)\),?$~m", $this->mainTableCreateStatement, $matches);
        if (!empty($matches)) {
            $this->primaryKeyDefinition = $matches[1];
        }
        return $this->primaryKeyDefinition;
    }

    /**
     * Get the names and definitions of any UNIQUE KEYs in the table to be audited. We need this
     * because the unique data in that table almost certainly will not be unique in the audit table.
     * So, we will drop each UNIQUE KEY in the audit table immediately after we create the table and
     * replace that UNIQUE KEY with a vanilla KEY with the same definition.
     * Will return an array of strings like array('id', 'first_name', 'last_name', 'balance', ...)
     *
     * @returns string[]
     * @since Version 0.0.1
     */
    private function getMainTableUniqueKeys()
    {
        preg_match_all("~^\s*UNIQUE\s+(KEY .+?),?$~m", $this->mainTableCreateStatement, $matches);
        if (!empty($matches)) {
            $this->uniqueKeys = $matches[1];
        }
        return $this->uniqueKeys;
    }

    /**
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getThrowErrorIfAuditTableExists()
    {
        return $this->throwErrorIfAuditTableExists;
    }

    /**
     * @return boolean
     * @codeCoverageIgnore
     */
    public function getThrowErrorIfTriggersExist()
    {
        return $this->throwErrorIfTriggersExist;
    }

    /**
     * @param mixed $throwErrorIfAuditTableExists
     */
    public function setThrowErrorIfAuditTableExists($throwErrorIfAuditTableExists)
    {
        $this->throwErrorIfAuditTableExists = $throwErrorIfAuditTableExists;
    }

    /**
     * @param boolean $throwErrorIfTriggersExist
     */
    public function setThrowErrorIfTriggersExist($throwErrorIfTriggersExist)
    {
        $this->throwErrorIfTriggersExist = $throwErrorIfTriggersExist;
    }
}