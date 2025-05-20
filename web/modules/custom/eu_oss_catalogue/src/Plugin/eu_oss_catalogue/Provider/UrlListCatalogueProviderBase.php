<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Plugin\ConfigurableInterface;
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
 *
 * @see \Drupal\Tests\eu_oss_catalogue\Kernel\UrlListCatalogueProviderTest
 */
abstract class UrlListCatalogueProviderBase extends EuOssCatalogueProviderPluginBase implements ConfigurableInterface {

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

  /**
   * The processed URLs.
   *
   * @var string[]
   */
  protected array $processedUrls = [];

  /**
   * Number of items in the list of the provider URLs.
   *
   * @var int
   */
  protected int $numberOfItems = 0;

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
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
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
  public function getConfiguration(): array {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'rootKeys' => [],
      'urlKeys' => [],
      'endpoints' => [],
      'filesToFetch' => [
        'publiccodeYml' => 'publiccode.yml',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    if (!isset($this->fetched)) {
      $this->requestData();
    }

    $collectedExceptions = [];
    foreach ($this->fetched as $baseUrl => $namespaces) {
      unset($this->fetched[$baseUrl]);

      $platformConfig = [
        'baseUrl' => $baseUrl,
        'projects' => $namespaces['projects'],
      ];
      $hostingPlatform = $this->platformManager->createInstance($namespaces['api']->pluginId, $platformConfig);
      assert($hostingPlatform instanceof EuOssCatalogueHostingPlatformInterface);

      try {
        foreach ($hostingPlatform->getProjects($this->getFilesToFetch()) as $project) {
          assert($project instanceof Project);
          $this->processedUrls[] = $project->url;
          $projectPreprocessed = $this->preprocessProject($project);
          if ($projectPreprocessed) {
            yield $projectPreprocessed;
          }
        }
      }
      catch (HostingPlatformFetchException $exception) {
        // Collect provider fetch exceptions, and throw them at the end of the
        // fetch operation.
        $collectedExceptions[] = $exception->getMessage();
      }
    }

    $this->logResults();

    if (!empty($collectedExceptions)) {
      throw new ProviderFetchException(implode(PHP_EOL, $collectedExceptions));
    }
  }

  /**
   * Processes the given projects.
   *
   * This method allows subclasses to align the projects right before they're
   * returned in the iterator of ::fetch().
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project to preprocess.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Project|null
   *   A preprocessed project object, or NULL if the given item must be skipped.
   */
  protected function preprocessProject(Project $project): ?Project {
    return isset($project->files['publiccodeYml'])
      ? $project
      : NULL;
  }

  /**
   * Returns the API endpoints.
   *
   * @return string[]
   *   Returns the API endpoints of the configured catalogue.
   */
  protected function getEndpoints(): array {
    return $this->getConfiguration()['endpoints'];
  }

  /**
   * Returns keys to the common ancestor list containing the solution URLs.
   *
   * @return string[]
   *   Returns the keys to the common ancestor list containing the solution
   *   URLs.
   */
  protected function getRootKeys(): array {
    return $this->getConfiguration()['rootKeys'];
  }

  /**
   * Returns keys to the solution URL in each list item.
   *
   * @return string[]
   *   Keys to the solution URL.
   */
  protected function getUrlKeys(): array {
    return $this->getConfiguration()['urlKeys'];
  }

  /**
   * Returns which files should be fetched from the posting platform.
   *
   * @return array<string, string>
   *   Keyed list of the files to fetch from the hosting platform. Must contain
   *   a key "publiccodeYml".
   */
  protected function getFilesToFetch(): array {
    return $this->getConfiguration()['urlKeys'];
  }

  /**
   * Requests data.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function requestData(): void {
    $this->fetched = [];

    foreach ($this->getEndpoints() as $endpointUrl) {
      try {
        $response = $this->httpClient->get($endpointUrl);
        $decodedResponse = Json::decode($response->getBody()->getContents());
        if (!is_array($decodedResponse)) {
          throw new \Exception('API returned response is not a valid JSON response.');
        }
        $value = NestedArray::getValue($decodedResponse, $this->getRootKeys(), $keyExists);
        if (!$keyExists) {
          throw new \Exception("The configured root keys don't exist on the decoded API response.");
        }
        $this->numberOfItems += count($value);
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
  private function processResponseData(array $responseData): void {
    $catalogueLabel = (string) $this->pluginDefinition['label'];

    foreach ($responseData as $row) {
      $rawUrl = NestedArray::getValue($row, $this->getUrlKeys(), $urlExists);
      $logContext = [
        '@catalogue-label' => $catalogueLabel,
        '@solution-repo-url' => $rawUrl,
        '@url-export' => var_export($rawUrl, TRUE),
      ];
      if (!$urlExists) {
        $this->logger->warning(
          'No URL property found at an item in @catalogue-label catalogue provider.',
          $logContext,
        );
        continue;
      }

      if (!is_string($rawUrl)) {
        $this->logger->warning(
          'Wrong data type at the URL property of an item in @catalogue-label catalogue provider: @url-export',
          $logContext,
        );
        continue;
      }

      $apiGuess = $this->apiGuesser->getHostingPlatformApi($rawUrl);
      if (!$apiGuess) {
        $this->logger->warning(
          'Cannot determine hosting platform for the repository with @solution-repo-url provided by the @catalogue-label catalogue.',
          $logContext,
        );
        continue;
      }

      $gitUri = GitUri::fromUriString($rawUrl);
      $name = $gitUri->name;
      $namespace = $gitUri->namespace;
      if (in_array($gitUri->getUri(), $this->fetchedUrls, TRUE)) {
        // This URI was already fetched from this catalogue.
        continue;
      }

      $this->fetched[$apiGuess->baseUrl]['api'] = $apiGuess;
      $this->fetched[$apiGuess->baseUrl]['projects'][$namespace][] = $name;
      $this->fetchedUrls[] = $gitUri->getUri();
    }
  }

  /**
   * Logs statistics about fetched and processed OSS Solutions.
   */
  private function logResults(): void {
    $catalogueLabel = (string) $this->pluginDefinition['label'];
    $notProcessable = array_diff($this->fetchedUrls, $this->processedUrls);
    $context = [
      '@processed-count' => count($this->processedUrls),
      '@fetched-count' => $this->numberOfItems,
      '@catalogue-label' => $catalogueLabel,
      '@unprocessed-count' => count($notProcessable),
      '@unprocessed-list' => implode(', ', $notProcessable),
    ];

    if (empty($notProcessable)) {
      $this->logger->info(
        'Processed @processed-count solutions out of @fetched-count items fetched from @catalogue-label catalogue provider.',
        $context,
      );
      return;
    }

    $this->logger->warning(
      'Processed @processed-count solutions out of @fetched-count items fetched from @catalogue-label catalogue provider. The following @unprocessed-count solutions failed: @unprocessed-list',
      $context,
    );
  }

}
