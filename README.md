#Cereblitz Audit Table Creator

This is a PHP class to log changes to the data in a MySQL table. MyISAM and InnoDB tables are supported; other engines
may or may not work correctly.

##Installation

Simply `include` or `require` the file `AuditTableCreator.php` in your PHP project.

##Usage

The constructor for AuditTableCreator is defined as:


Adding auditing to a table requires two basic steps:

  1. create an instance of `AuditTableCreator` and pass it
    a. the name of an existing table to be audited and
    b. a database connection, then
  2. invoke the `execute()` method.

For example:

    $atc = new AuditTableCreator('myExistingTable', $myConnection);
    $atc->execute();

The database connection may be an instance of the `PDO` or `mysqli` classes. It may also be an other class that exposes
a `query()` method for running queries on a MySQL database. The `query()` method, however, must return an object that
exposes either a `fetch()` method (like `PDOStatement`) or a `fetch_array()` method (like `mysqli_stmt`).

If, for some reason, you would like to retrieve an array of the SQL commands to be executed without actually executing
them, use the `generateSQLStatements()` method instead:

    $atc = new AuditTableCreator('myExistingTable', $myConnection);
    $statements = $atc->generateSQLStatements(); // an array of strings (SQL queries)

But note that the class will still need to run some queries to prepare these strings; it cannot determine how to log
changes to the existing table without first determining that table's structure.

When constructing the class, you may pass an optional third parameter to specify the name of the audit table. By
default, it is `audit_YOUR_TABLE`, where `YOUR_TABLE` is the name of the original table.

##Testing and Development

Testing and development require a few composer and grunt packages. To contribute to this repository or run unit tests
on it, run these commands:

    $ composer install
    $ npm install grunt
    $ grunt install

Pull requests are appreciated!