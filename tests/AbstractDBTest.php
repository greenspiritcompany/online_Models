<?php
namespace Telltale\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;
use Psr6NullCache\Adapter\NullCacheItemPool;

abstract class AbstractDBTest extends TestCase {
    use TestCaseTrait;

    // only instantiate pdo once for test clean-up/fixture load
    static private $pdo = null;

    // Cache tables setup within the pdo instance
    static private $tables = [];

    // Null cache pool container
    private $cache = null;

    // only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
    private $conn = null;

    /**
     * Returns the test database connection.
     *
     * @return PHPUnit\DbUnit\Database\Connection
     */
    final public function getConnection() {
      if ($this->conn === null) {
        if (self::$pdo == null) {
            self::$pdo = new \PDO('sqlite::memory:');
        }
        $this->conn = $this->createDefaultDBConnection(self::$pdo, ':memory:');
      }

      return $this->conn;
    }

    /**
     * Get PDO from the connection
     *
     * @return PDO
     */
    protected function getPDO() {
      return $this->getConnection()->getConnection();
    }

    /**
     * Get a null item cache pool for testing
     *
     * @return void
     */
    protected function getCache() {
      if ($this->cache === null) {
        $this->cache = new NullCacheItemPool();
      }
      return $this->cache;
    }

    /**
     * Setup the schema for a table
     *
     * @param string $table
     * @param string $schema
     * @return void
     */
    protected function setupTableSchema(string $table, string $schema) {
      if (!in_array($table, self::$tables)) {
        $this->getPDO()->exec("CREATE TABLE `{$table}` ({$schema});");
        self::$tables[] = $table;
      }
    }

}