<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Commands;

use Drupal\Core\Database\Connection;
use Drush\Commands\DrushCommands;

/**
 * Commands related to the cache tables.
 *
 * @see \Drush\Drupal\Commands\sql\SanitizeCommands::sanitize()
 */
class JoinupCleanCacheTablesCommands extends DrushCommands {

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection.
   */
  public function __construct(protected Connection $db) {
    parent::__construct();
  }

  /**
   * Cleans the cahce tables.
   *
   * @command joinup:clean-cache-tables
   */
  public function cleanCacheTables(): void {
    // Load all tables from the database that start with `cache_`.
    $tables = $this->db->schema()->findTables('cache_%');

    // Truncate all cache tables.
    foreach ($tables as $table) {
      $this->db->truncate($table)->execute();
    }
  }

}
