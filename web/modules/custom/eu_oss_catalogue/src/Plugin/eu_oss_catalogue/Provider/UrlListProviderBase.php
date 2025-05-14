<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eu_oss_catalogue\ApiGuesserInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Exception\ProviderFetchException;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Drupal\eu_oss_catalogue\Model\Project;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin class of providers returning a list with repo URIs.
 */
abstract class UrlListProviderBase extends EuOssCatalogueProviderPluginBase {

  /**
   * Keys to the actual catalogue list.
   *
   * @var string|array
   */
  protected string|array $rootKeys;

  /**
   * Keys to the repository URL inside a catalogue's own row.
   *
   * @var string|array
   */
  protected string|array $urlKeys;

  /**
   * Catalogue endpoints.
   *
   * @var string[]
   */
  protected const array ENDPOINTS = [];

  /**
   * List of projects fetched from API.
   *
   * @var array{'api': \Drupal\eu_oss_catalogue\Model\ApiGuess, 'projects': array{string: string}}
   *   A list of fetched projects keyed by the coding platform base URL. The
   *   values are associative array of project IDs keyed by their namespace.
   */
  protected array $fetched;

  /**
   * Fetched URLs collector.
   *
   * @var string[]
   */
  protected array $fetchedUrls = [];

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected LoggerChannelInterface $logger,
    protected Client $httpClient,
    protected ApiGuesserInterface $apiGuesser,
    protected EuOssCataloguePluginManagerInterface $platformManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $parserManager, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.eu_oss_catalogue.parser'),
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('http_client'),
      $container->get('eu_oss_catalogue.git_remote.api_guesser'),
      $container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    if (!isset($this->fetched)) {
      $this->requestData();
    }

    foreach ($this->fetched as $baseUrl => $namespaces) {
      unset($this->fetched[$baseUrl]);

      $platformConfig = [
        'baseUrl' => $baseUrl,
        'projects' => $namespaces['projects'],
      ];

      try {
        $hostingPlatform = $this->platformManager->createInstance($namespaces['api']->pluginId, $platformConfig);
        assert($hostingPlatform instanceof EuOssCatalogueHostingPlatformInterface);
        foreach ($hostingPlatform->getProjects(['publiccodeYml' => 'publiccode.yml']) as $project) {
          assert($project instanceof Project);
          if (isset($project->files['publiccodeYml'])) {
            yield $project;
          }
        }
      }
      catch (PluginException | HostingPlatformFetchException) {
      }
    }
  }

  /**
   * Requests data.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function requestData(): void {
    $this->fetched = [];

    foreach (static::ENDPOINTS as $endpointUrl) {
      try {
        $response = $this->httpClient->get($endpointUrl);
        $decodedResponse = Json::decode($response->getBody()->getContents());
        if (!is_array($decodedResponse)) {
          throw new ProviderFetchException(
            'API returned response is not a valid JSON response.',
          );
        }
        $value = NestedArray::getValue($decodedResponse, (array) $this->rootKeys, $keyExists);
        if (!$keyExists) {
          throw new ProviderFetchException(
            "The configured root keys don't exist on the decoded API response."
          );
        }
        $this->processResponseData($value);
      }
      catch (\Exception $exception) {
        throw new ProviderFetchException(
          $exception->getMessage()
        );
      }
    }
  }

  /**
   * Processes response data.
   *
   * @param array $responseData
   *   Response data.
   */
  protected function processResponseData(array $responseData): void {
    foreach ($responseData as $row) {
      $rawUrl = NestedArray::getValue($row, (array) $this->urlKeys, $urlExists);
      if (!$urlExists || !is_string($rawUrl)) {
        $this->logger->warning('Wrong data type');
        continue;
      }

      $apiGuess = $this->apiGuesser->getHostingPlatformApi($rawUrl);
      if (!$apiGuess) {
        continue;
      }

      $gitUri = GitUri::fromUriString($rawUrl);
      $name = $gitUri->name;
      $namespace = $gitUri->namespace;
      if (in_array($gitUri->getUri(), $this->fetchedUrls)) {
        continue;
      }

      $this->fetched[$apiGuess->baseUrl]['api'] = $apiGuess;
      $this->fetched[$apiGuess->baseUrl]['projects'][$namespace][] = $name;
      $this->fetchedUrls[] = $gitUri->getUri();
    }
  }

}
