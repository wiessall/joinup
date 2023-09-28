<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eu_oss_catalogue\ApiGuesserInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Developers Italia provider.
 *
 * @EuOssCatalogueProvider(
 *   id = "developers_italia",
 *   label = @Translation("Developers Italia"),
 *   description = @Translation("Italian National OSS Catalogue, by https://developers.italia.it website."), parser = "publiccode_yml",
 * )
 */
class DevelopersItalia extends EuOssCatalogueProviderPluginBase {

  use SchemaCheckTrait {
    checkValue as traitCheckValue;
  }

  /**
   * Developers Italia API endpoint.
   *
   * @var string
   */
  protected const ENDPOINT = 'https://api.developers.italia.it/v1/software';

  /**
   * List of projects fetched from Developers Italia API.
   *
   * @var array[][]
   *   A list of fetched projects keyed by the coding platform base URL. The
   *   values are associative arrays keyed by namespace and having a list of
   *   project IDs as values.
   */
  protected array $fetched;

  /**
   * Fetched URLs collector.
   */
  protected array $fetchedUrls = [];

  /**
   * Processed URLs collector.
   */
  protected array $processedUrls = [];

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $parserManager
   *   The EU OSS Catalogue parser plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $platformManager
   *   The EU OSS Catalogue code hosting platform plugin manager service.
   * @param \Drupal\eu_oss_catalogue\ApiGuesserInterface $apiGuesser
   *   The API guesser service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected LoggerChannelInterface $logger,
    protected Client $httpClient,
    protected EuOssCataloguePluginManagerInterface $platformManager,
    protected ApiGuesserInterface $apiGuesser,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $parserManager, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('plugin.manager.eu_oss_catalogue.parser'),
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('http_client'),
      $container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
      $container->get('eu_oss_catalogue.git_remote.api_guesser'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    if (!isset($this->fetched)) {
      $this->fetched = [];
      $this->doFetch();
    }

    foreach ($this->fetched as $baseUrl => $namespaces) {
      // Remove this entry to avoid fetching again on the next iteration.
      unset($this->fetched[$baseUrl]);

      if (!$platformId = $this->guessPlatformPluginId($baseUrl)) {
        continue;
      }

      $platformConfig = ['projects' => $namespaces, 'url' => $baseUrl];
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $hostingPlatform */
      $hostingPlatform = $this->platformManager->createInstance($platformId, $platformConfig);

      /** @var \Drupal\eu_oss_catalogue\Model\Project $project */
      foreach ($hostingPlatform->getProjects(['publiccodeYml' => 'publiccode.yml']) as $project) {
        $this->processedUrls[] = $project->url;
        yield $project;
      }

      // Only log when all solutions were processed.
      if (empty($this->fetched)) {
        $this->logResults();
      }
    }
  }

  /**
   * Fetches data from Developers Italia API endpoint.
   */
  protected function doFetch(): void {
    $next = '';

    do {
      $endpointUrl = static::ENDPOINT . $next . ($next ? '&' : '?') . 'page[size]=100&all=true';
      $response = $this->httpClient->get($endpointUrl);
      $read = Json::decode($response->getBody()->getContents());

      foreach ($read['data'] as $newItem) {
        if (empty($newItem['url'])) {
          $this->logger->error("Item @id lacks the mandatory URL", ['@id' => $newItem['id']]);
          continue;
        }

        $url = preg_replace('/^(.*)\.git$/', '$1', $newItem['url']);
        $urlParts = parse_url($url);
        $baseUrl = http_build_url([
          'scheme' => $urlParts['scheme'],
          'host' => $urlParts['host'],
          'port' => $urlParts['port'] ?? '',
        ]);

        // @todo Assuming that the code hosting platform is always installed
        //   beneath the domain, not in a subdirectory.
        // @todo This should be adapted after ISAICP-7964 lands.
        // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7964
        // @see https://github.com/italia/developers-italia-api/issues/215
        $pathParts = explode('/', trim($urlParts['path'], '/'));
        $name = array_pop($pathParts);
        $namespace = implode('/', $pathParts);

        $this->fetched[$baseUrl][$namespace][] = $name;
        $this->fetchedUrls[] = $url;
      }

      $next = $read['links']['next'];
    } while ($next !== NULL);
  }

  /**
   * Logs statistics about fetched and processed OSS Solutions.
   */
  protected function logResults(): void {
    if ($unProcessed = array_diff($this->fetchedUrls, $this->processedUrls)) {
      $this->logger->warning('Processed {processed} out of {fetched} fetched solutions from Developers Italia. The following {unprocessed} solutions failed: ' . implode(', ', $unProcessed), [
        'processed' => count($this->processedUrls),
        'fetched' => count($this->fetchedUrls),
        'unprocessed' => count($unProcessed),
        'list' => $unProcessed,
      ]);
    }
    else {
      $this->logger->info('Processed {processed} out of {fetched} fetched solutions from Developers Italia.', [
        'processed' => count($this->processedUrls),
        'fetched' => count($this->fetchedUrls),
      ]);
    }
  }

  /**
   * Provides a temporary API guesser until ISAICP-7964.
   *
   * @param string $url
   *   The URL.
   *
   * @return string|null
   *   The guessed code hosting platform plugin ID or NULL if none.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7964
   *
   * @todo Remove in ISAICP-7964.
   */
  private function guessPlatformPluginId(string $url): ?string {
    return match(TRUE) {
      // -----------------
      // Developers Italia
      // -----------------
      $url === 'https://github.com' => 'github',
      $url === 'https://gitlab.com' => 'gitlab_com',
      // Includes some self-hosted.
      str_starts_with($url, 'https://gitlab') => 'gitlab_self_hosted',
      $url === 'https://invent.kde.org' => 'gitlab_self_hosted',
      $url === 'https://oros-git.regione.puglia.it' => 'gitlab_self_hosted',
      $url === 'https://riuso.comune.salerno.it' => 'gitlab_self_hosted',
      // Not supported yet.
      // $url === 'https://bitbucket.org' => 'bitbucket',
      // -------------------------
      // Developers Italia - tests
      // -------------------------.
      $url === 'https://limited.self-hosted.gitlab.example.com' => 'gitlab_self_hosted',
      default => NULL,
    };
  }

}
