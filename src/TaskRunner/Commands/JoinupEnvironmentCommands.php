<?php

declare(strict_types=1);

namespace Joinup\TaskRunner\Commands;

use Behat\Behat\ApplicationFactory;
use Drupal\Core\Database\Database;
use EasyRdf\Http;
use EcEuropa\Toolkit\TaskRunner\AbstractCommands;
use GuzzleHttp\Client;
use PHPUnit\Runner\Version;
use Psr\Http\Message\ResponseInterface;
use Robo\Exception\AbortTasksException;
use Robo\Robo;

/**
 * Portal Environment Commands.
 */
class JoinupEnvironmentCommands extends AbstractCommands {

  /**
   * Displays information about Portal environment.
   *
   * @command joinup:environment
   *
   * @return string
   *   A table with Portal environment info.
   */
  public function joinupEnvironment(): string {
    $php_version = phpversion();
    // phpcs:disable
    return "Environment:\n" .
      " PHP      {$php_version}\n" .
      " MySQL    {$this->mysqlVersion()} (or MariaDB, Percona Server, or equivalent)\n" .
      " Virtuoso {$this->virtuosoVersion()}\n" .
      " Solr     {$this->solrVersion()}\n" .
      " PHPUnit  {$this->phpunitVersion()}\n" .
      " Behat    {$this->behatVersion()}\n" .
      " Selenium {$this->seleniumVersion()}\n" .
      " Node     {$this->nodeVersion()}\n" .
      " NPM      {$this->npmVersion()}\n" .
      " Portal   {$this->joinupVersion()}";
  }

  /**
   * Returns the MySQL server version.
   *
   * @return string
   *   The MySQL version.
   */
  protected function mysqlVersion(): string {
    /** @var \Composer\Autoload\ClassLoader $classLoader */
    $class_loader = Robo::getContainer()->get('classLoader');
    // As Drupal is not bootstrapped, the module PSR4 autoload mappings are not
    // set. We manually register the PSR4 prefix.
    // @see https://www.drupal.org/node/3129492
    $class_loader->addPsr4('Drupal\\mysql\\',  'web/core/modules/mysql/src');

    $config = $this->getConfig();
    $info = Database::parseConnectionInfo([
      'database' => $config->get('env.DRUPAL_DATABASE_NAME'),
      'username' => $config->get('env.DRUPAL_DATABASE_USERNAME'),
      'password' => $config->get('env.DRUPAL_DATABASE_PASSWORD'),
      'host' => $config->get('env.DRUPAL_DATABASE_HOST'),
      'port' => $config->get('env.DRUPAL_DATABASE_PORT'),
      'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
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
    $info = json_decode($this->httpGet($this->getSolrUrl() . "/admin/info/system?wt=json")->getBody()->getContents(), TRUE);
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

  /**
   * Returns the Portal version.
   *
   * @return string
   *   The Portal version.
   */
  protected function joinupVersion(): string {
    $config = $this->getConfig();
    $file = "{$config->get('joinup.site_dir')}/VERSION.txt";
    if ($version = @file_get_contents($file)) {
      return $version;
    }
    return '(version unknown)';
  }

  /**
   * Returns the Selenium version.
   *
   * @return string
   *   The Selenium version.
   */
  protected function seleniumVersion(): string {
    try {
      $webdriver_url = $this->getConfig()->get('env.WEBDRIVER_URL');
      $status = json_decode($this->httpGet("$webdriver_url/status")->getBody()->getContents(), TRUE);
    }
    catch (\Exception $e) {
      return '(cannot determine)';
    }

    if (isset($status['value']['nodes'][0]['version'])) {
      return $status['value']['nodes'][0]['version'];
    }
    elseif (isset($status['value']['build']['version'])) {
      return $status['value']['build']['version'];
    }
    return '(version unknown)';
  }

  /**
   * Returns the PHPUnit version.
   *
   * @return string
   *   The PHPUnit version.
   */
  protected function phpunitVersion(): string {
    return Version::id();
  }

  /**
   * Returns the Behat version.
   *
   * @return string
   *   The Behat version.
   */
  protected function behatVersion(): string {
    return ApplicationFactory::VERSION;
  }

  /**
   * Returns the Node version.
   *
   * @return string
   *   The Node version.
   */
  protected function nodeVersion(): string {
    return exec('node --version') ?: '(cannot determine)';
  }

  /**
   * Returns the NPM version.
   *
   * @return string
   *   The NPM version.
   */
  protected function npmVersion(): string {
    return exec('npm --version') ?: '(cannot determine)';
  }

  /**
   * Returns the SOLR URL including the path.
   *
   * @return string
   *   The URL of SOLR endpoint, including the path.
   */
  protected function getSolrUrl(): string {
    return http_build_url([
      'scheme' => getenv('SEARCH_API_SERVER_SS_SOLR_SCHEME') ?: 'http',
      'host' => getenv('SEARCH_API_SERVER_SS_SOLR_HOST'),
      'port' => getenv('SEARCH_API_SERVER_SS_SOLR_PORT'),
      'path' => 'solr',
    ]);
  }


  /**
   * Performs an HTTP get request.
   *
   * @param string $url
   *   The HTTP get request URL.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response
   *
   * @throws \Robo\Exception\AbortTasksException|\GuzzleHttp\Exception\GuzzleException
   *   When the server response is invalid.
   */
  protected function httpGet(string $url): ResponseInterface {
    try {
      $response = (new Client())->get($url);
    }
    catch (\Exception $exception) {
      throw new AbortTasksException($exception->getMessage(), 0, $exception);
    }
    if ($response->getStatusCode() != 200) {
      throw new AbortTasksException("Server returned HTTP code {$response->getStatusCode()}, while querying {$url}.");
    }

    return $response;
  }

}
