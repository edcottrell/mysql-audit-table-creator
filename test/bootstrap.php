<?php
/**
 * Bootstrap file for PHPUnit tests
 */

namespace Cereblitz;

require_once __DIR__ . "/../src/AuditTableCreator.php";
require_once "test-credentials.php";

if(defined('AUDIT_TABLE_CREATOR_BOOTSTRAPPED')) {
    exit();
}

define('AUDIT_TABLE_CREATOR_BOOTSTRAPPED', true);

/**
 * Connect to a test database
 */
class AuditTableCreatorDbTestConnection {
    /** @var bool Flag for whether the connection is active or not */
    public static $connected = false;

    /** @var null|\mysqli The real connection */
    private static $connectionObject = null;

    /**
     * @return \mysqli
     * @throws \Exception
     */
    public static function connect()
    {
        if (!self::$connected) {
            self::$connectionObject = new \mysqli(AUDIT_TEST_DB_SERVER, AUDIT_TEST_DB_USER, AUDIT_TEST_DB_PASSWORD, AUDIT_TEST_DB_SCHEMA, AUDIT_TEST_DB_PORT);
            if (self::$connectionObject) {
                if (self::$connectionObject->errno) {
                    throw new \Exception('MySQLi connection failed in ' . __CLASS__ . "\n" .
                        "Error code: " . self::$connectionObject->errno . "\n" .
                        "Error message: ". self::$connectionObject->error);
                }
            } else {
                throw new \Exception('MySQLi connection failed in ' . __CLASS__ . ", reason unknown");
            }
            self::$connected = true;
        }
        return self::$connectionObject;
    }

    /**
     * @return bool True if the disconnect was successful; false if it was not (e.g., because no connection existed)
     */
    public static function disconnect()
    {
        if (self::$connected) {
            self::$connectionObject->close();
            return true;
        }
        return false;
    }
}