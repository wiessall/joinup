<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Commands;

use Drupal\Core\Database\Database;
use EasyRdf\Http;
use Joinup\TaskRunner\Traits\SolrTrait;
use OpenEuropa\TaskRunner\Commands\AbstractCommands;

/**
 * Joinup Environment Commands.
 */
class JoinupEnvironmentCommands extends AbstractCommands {

  use SolrTrait;

  /**
   * Displays information about Joinup environment.
   *
   * @command joinup:environment
   *
   * @return string
   *   A table with Joinup environment info.
   */
  public function joinupEnvironment(): string {
    $php_version = phpversion();
    // phpcs:disable
    return "Environment:\n" .
      " PHP      {$php_version}\n" .
      " MySQL    {$this->mysqlVersion()} (or MariaDB, Percona Server, or equivalent)\n" .
      " Virtuoso {$this->virtuosoVersion()}\n" .
      " Solr     {$this->solrVersion()}\n" .
      " Joinup   {$this->joinupVersion()}\n";
  }

  /**
   * Returns the MySQL server version.
   *
   * @return string
   *   The MySQL version.
   */
  protected function mysqlVersion(): string {
    $config = $this->getConfig();
    $info = Database::parseConnectionInfo([
      'database' => $config->get('env.DRUPAL_DATABASE_NAME'),
      'username' => $config->get('env.DRUPAL_DATABASE_USERNAME'),
      'password' => $config->get('env.DRUPAL_DATABASE_PASSWORD'),
      'host' => $config->get('env.DRUPAL_DATABASE_HOST'),
      'port' => $config->get('env.DRUPAL_DATABASE_PORT'),
      'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
      'driver' => 'mysql',
    ]);
    Database::addConnectionInfo('default', 'default', $info);
    return Database::getConnection()->version();
  }

  /**
   * Returns the Virtuoso server version.
   *
   * @return string
   *   The Virtuoso server version.
   */
  protected function virtuosoVersion(): string {
    $config = $this->getConfig();
    $client = Http::getDefaultHttpClient();
    $client->resetParameters(TRUE);
    $client->setUri("http://{$config->get('env.DRUPAL_SPARQL_HOSTNAME')}:{$config->get('env.DRUPAL_SPARQL_PORT')}/");
    $client->setMethod('GET');
    $response = $client->request();
    $server_header = $response->getHeader('Server');
    [$version] = explode(' ', $server_header, 2);
    return substr($version, 9);
  }

  /**
   * Returns the Solr server version.
   *
   * @return string
   *   The Solr server version
   */
  protected function solrVersion(): string {
    $config = $this->getConfig();
    $info = json_decode($this->httpGet("{$config->get("env.SOLR_URL")}/admin/info/system?wt=json")->getBody()->getContents(), TRUE);
    $version = '0.0.0';
    if (isset($info['lucene']['solr-spec-version'])) {
      // Some Solr distributions or docker images append additional info to the
      // version number, for example the build date: 3.6.2.2012.12.18.19.52.27.
      if (preg_match('/^(\d+\.\d+\.\d+)/', $info['lucene']['solr-spec-version'], $matches)) {
        $version = $matches[1];
      }
    }
    return $version;
  }

  protected function joinupVersion(): string {
    $config = $this->getConfig();
    $file = "{$config->get('joinup.site_dir')}/VERSION.txt";
    if ($version = @file_get_contents($file)) {
      return $version;
    }
    return '(version unknown)';
  }

}
