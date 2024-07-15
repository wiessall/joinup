<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\ApiGuesserInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Exception\ProviderFetchException;
use Drupal\eu_oss_catalogue\Model\Project;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Developers Italia provider.
 *
 * @EuOssCatalogueProvider(
 *   id = "developers_italia",
 *   label = @Translation("Developers Italia"),
 *   description = @Translation("Italian National OSS Catalogue, by https://developers.italia.it website."),
 *   parser = "publiccode_yml",
 * )
 */
class DevelopersItalia extends EuOssCatalogueProviderPluginBase {

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
   * YAML content from developers.italia.it.
   */
  protected array $yamlContent = [];

  /**
   * Number of solutions provided by API.
   *
   * @var int
   */
  protected int $numberOfProvidedSolutions = 0;

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
      $this->numberOfProvidedSolutions = 0;
      $this->doFetch();
    }

    $collectedExceptions = [];
    foreach ($this->fetched as $baseUrl => $namespaces) {
      // Remove this entry to avoid fetching again on the next iteration.
      unset($this->fetched[$baseUrl]);

      $platformConfig = [
        'baseUrl' => $baseUrl,
        'projects' => $namespaces['projects'],
      ];
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $hostingPlatform */
      $hostingPlatform = $this->platformManager->createInstance($namespaces['api']->pluginId, $platformConfig);

      /** @var \Drupal\eu_oss_catalogue\Model\Project $project */
      try {
        foreach ($hostingPlatform->getProjects(['publiccodeYml' => 'publiccode.yml']) as $project) {
          $this->processedUrls[] = $project->url;

          // Try to use publiccodeYml from the Git repository if it is provided.
          if (isset($project->files['publiccodeYml']) || !isset($this->yamlContent[$project->url])) {
            yield $project;
            continue;
          }

          // Drop to autonomous Git Repos that simply host a publiccode.yml file
          // that points to the target OSS Solution GIT repo
          // (that doesn't host a publiccode.yml, yet ... ).
          $values = $project->toArray();
          $values['files']['publiccodeYml'] = $this->yamlContent[$project->url];
          yield new Project(...$values);
        }
      }
      catch (HostingPlatformFetchException $exception) {
        // Collect provider fetch exceptions, and throw them at the end of the
        // fetch operation.
        $collectedExceptions[] = $exception->getMessage();
      }

      // Only log when all solutions were processed.
      if (empty($this->fetched)) {
        $this->logResults();
      }
    }

    if (!empty($collectedExceptions)) {
      throw new ProviderFetchException(implode(PHP_EOL, $collectedExceptions));
    }
  }

  /**
   * Fetches data from Developers Italia API endpoint.
   */
  protected function doFetch(): void {
    $next = '';

    do {
      $endpointUrl = static::ENDPOINT . $next . ($next ? '&' : '?') . 'page[size]=100&all=false';
      try {
        $response = $this->httpClient->get($endpointUrl);
        $read = Json::decode($response->getBody()->getContents());
      }
      catch (\Exception $exception) {
      }
      if (isset($exception) || $response->getStatusCode() >= 400) {
        $message = $exception?->getMessage() ?? $response->getReasonPhrase();
        throw new ProviderFetchException(
          $message,
          0,
          $exception ?? NULL
        );
      }

      $this->numberOfProvidedSolutions += count($read['data']);
      foreach ($read['data'] as $newItem) {
        $projectInfo = self::ENDPOINT . '/' . $newItem['id'];
        try {
          $decoded = Yaml::decode($newItem['publiccodeYml']);
        }
        catch (InvalidDataTypeException $e) {
          $this->logger->error('Not valid publiccodeYml for @id. Message: @message', [
            '@id' => $projectInfo,
            '@message' => $e->getMessage(),
          ]);
          continue;
        }

        if (empty($decoded['url'])) {
          $this->logger->error('Item @id lacks the mandatory URL in publiccodeYml', ['@id' => $projectInfo]);
          continue;
        }

        if (!$api = $this->apiGuesser->getHostingPlatformApi($decoded['url'])) {
          $this->logger->error('Can\'t determinate hosting platform for @id with @repository repository',
            ['@id' => $projectInfo, '@repository' => $decoded['url']]);
          continue;
        }

        $url = preg_replace('/^(.*)\.git$/', '$1', $decoded['url']);
        $pathParts = explode('/', trim(substr($url, strlen($api->baseUrl)), '/'));
        $name = array_pop($pathParts);
        $namespace = implode('/', $pathParts);

        if (!isset($this->fetched[$api->baseUrl]['api'])) {
          $this->fetched[$api->baseUrl]['api'] = $api;
        }
        $this->fetched[$api->baseUrl]['projects'][$namespace][] = $name;
        $this->fetchedUrls[] = $url;
        $this->yamlContent[$url] = $newItem['publiccodeYml'];
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
        'fetched' => $this->numberOfProvidedSolutions,
        'unprocessed' => count($unProcessed),
        'list' => $unProcessed,
      ]);
    }
    else {
      $this->logger->info('Processed {processed} out of {fetched} fetched solutions from Developers Italia.', [
        'processed' => count($this->processedUrls),
        'fetched' => $this->numberOfProvidedSolutions,
      ]);
    }
  }

}
