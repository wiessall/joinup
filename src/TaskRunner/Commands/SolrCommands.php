<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Commands;

use Drupal\Component\Serialization\Json;
use Joinup\TaskRunner\Traits\SolrTrait;
use OpenEuropa\TaskRunner\Commands\AbstractCommands;
use Robo\Exception\AbortTasksException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Provides commands for ApacheSolr admin.
 */
class SolrCommands extends AbstractCommands {

  use SolrTrait;

  /**
   * Maximum status execution time in seconds.
   *
   * @var int
   */
  const MAX_EXECUTION_TIME = 120;

  /**
   * Command map. Used to determine the right Solr command to execute.
   *
   * @var string[][]
   *   An associative array of command data, keyed by the operation (either
   *   'backup' or 'restore'. Each data array has two possible commands, keyed
   *   by a boolean value that indicates whether we are performing a status
   *   check or executing the actual command. This value will be TRUE if we are
   *   performing a status check, and FALSE otherwise.
   */
  const COMMAND_MAP = [
    'backup' => [
      FALSE => 'backup',
      TRUE => 'details',
    ],
    'restore' => [
      FALSE => 'restore',
      TRUE => 'restorestatus',
    ],
  ];

  /**
   * Empties the Solr server core index.
   *
   * @command solr:empty
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When the server response is invalid.
   */
  public function empty(): void {
    $config = $this->getConfig();
    $core = $config->get('env.SEARCH_API_SERVER_SS_SOLR_CORE');
    $this->say("Empty Solr '{$core}' core.");
    $this->httpPost(
      self::getSolrUrl() . "/$core/update?commit=true&wt=json",
      '<delete><query>*:*</query></delete>',
      ['Content-Type' => 'application/xml'],
    );
    $this->say("Successfully emptied Solr '{$core}' core.");
  }

  /**
   * Takes a snapshot from the Solr server core index.
   *
   * @command solr:backup
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When the server response is invalid.
   */
  public function backup(): void {
    $config = $this->getConfig();
    $core = $config->get('env.SEARCH_API_SERVER_SS_SOLR_CORE');

    $this->say("Backup Solr '{$core}' core.");

    // Ensure there's no existing backup.
    $file_system = new Filesystem();
    $file_system->remove($config->get('solr.snapshot_dir') . "/snapshot.$core");

    $this->executeReplicationCommand($core, 'backup');
    $this->say("Successfully backed-up Solr '{$core}' core.");
  }

  /**
   * Restores the Solr server core index from a snapshot.
   *
   * @command solr:restore
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When the server response is invalid.
   */
  public function restore(): void {
    $config = $this->getConfig();
    $core = $config->get('env.SEARCH_API_SERVER_SS_SOLR_CORE');

    $this->say("Restoring Solr '{$core}' core.");

    // Ensure an empty core before proceeding.
    $this->empty();

    $this->executeReplicationCommand($core, 'restore');
    $this->say("Successfully restored Solr '{$core}' core.");
  }

  /**
   * Executes a command: backup or restore.
   *
   * @param string $core
   *   The core name for which to build the URL.
   * @param string $operation
   *   The operation to be performed: 'restore' or 'backup'.
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When the server response is invalid.
   */
  protected function executeReplicationCommand(string $core, string $operation): void {
    $this->httpGet($this->getUrl($operation));

    // Solr core backup and restore are asynchronous processes. In order to
    // consider the backup/restore done, we need to check the status of the last
    // process.
    $start = time();
    $attempt = 0;
    do {
      $status = $this->getOperationStatus($core, $operation, $attempt);

      // Status has timed-out.
      if (time() - $start > static::MAX_EXECUTION_TIME) {
        throw new AbortTasksException("Timed-out while getting the {$operation} status for the Solr '{$core}' core.");
      }

      if ($status === 'in progress') {
        // Wait 2 seconds before trying again.
        sleep(2);
      }
    } while ($status !== 'success');
  }

  /**
   * Gets the operation status.
   *
   * @param string $core
   *   The core name for which to build the URL.
   * @param string $operation
   *   The operation to be performed: 'restore' or 'backup'.
   * @param int $attempt
   *   The attempt number.
   *
   * @return string
   *   Could be: 'in progress', 'success', 'failed'.
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When the Solr server response is invalid.
   */
  protected function getOperationStatus(string $core, string $operation, int &$attempt): string {
    $attempt++;
    $this->say("Check if {$operation} is complete (attempt: #{$attempt})");

    $response = $this->httpGet($this->getOperationStatusUrl($operation));

    if (($content = Json::decode($response->getBody()->getContents())) === NULL) {
      throw new AbortTasksException("Invalid response from Solr server, while trying to get the {$operation} status of Solr '{$core}' core.");
    }

    if ($operation === 'backup') {
      $status_index = array_search('status', $content['details']['backup'] ?? [], TRUE);
      $status = $status_index !== FALSE ? $content['details']['backup'][$status_index + 1] : 'in progress';
    }
    elseif ($operation === 'restore') {
      $status = $content['restorestatus']['status'];
    }
    $status = strtolower($status);

    // Operation failed.
    if ($status === 'failed') {
      throw new AbortTasksException("Failed to {$operation}, with output:\n" . print_r($content, TRUE));
    }

    return $status;
  }

  /**
   * Builds the Solr replicate URL.
   *
   * @param string $operation
   *   The operation to be performed: 'restore' or 'backup'.
   *
   * @return string
   *   The URL.
   *
   * @see https://solr.apache.org/guide/8_11/making-and-restoring-backups.html
   */
  protected function getUrl(string $operation): string {
    $config = $this->getConfig();
    $command = static::COMMAND_MAP[$operation][FALSE];
    return self::getSolrUrl() . "/{$config->get("env.SEARCH_API_SERVER_SS_SOLR_CORE")}/replication?command={$command}&name={$config->get("env.SEARCH_API_SERVER_SS_SOLR_CORE")}&location={$config->get('solr.snapshot_dir')}";
  }

  /**
   * Builds the operation status URL.
   *
   * @param string $operation
   *   The operation to be performed: 'restore' or 'backup'.
   *
   * @return string
   *   The URL.
   */
  protected function getOperationStatusUrl(string $operation): string {
    $config = $this->getConfig();
    $command = static::COMMAND_MAP[$operation][TRUE];
    return self::getSolrUrl() . "/{$config->get("env.SEARCH_API_SERVER_SS_SOLR_CORE")}/replication?command={$command}";
  }

  /**
   * Returns the SOLR URL including the path.
   *
   * @return string
   */
  public static function getSolrUrl(): string {
    $url = getenv('SEARCH_API_SERVER_SS_SOLR_HOST')
      . ":" . getenv('SEARCH_API_SERVER_SS_SOLR_PORT')
      . "/" . trim(getenv('SEARCH_API_SERVER_SS_SOLR_PATH'), '/');
    return rtrim($url, '/');
  }

}
