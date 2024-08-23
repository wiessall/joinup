<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eu_oss_catalogue\ApiGuesserInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Exception\ProviderFetchException;
use Drupal\eu_oss_catalogue\Model\Project;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dutch catalogue provider.
 *
 * @EuOssCatalogueProvider(
 *   id = "dutch_catalogue",
 *   label = @Translation("Dutch catalogue"),
 *   description = @Translation("Dutch catalogue onboarding."),
 *   parser = "publiccode_yml",
 * )
 */
class DutchCatalogue extends EuOssCatalogueProviderPluginBase {

  /**
   * Developers Italia API endpoint.
   *
   * @var string
   */
  protected const string ENDPOINT = 'https://gist.githubusercontent.com/dvh/95a09cf9df9b4567c256c188a0e8f4b2/raw';

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
   * Number of solutions provided by API.
   *
   * @var int
   */
  protected int $numberOfProvidedSolutions = 0;

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
      $this->getData();
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
        foreach ($hostingPlatform->getProjects([
          'publiccodeYml' => 'publiccode.yml',
          'publiccodeYaml' => 'publiccode.yaml',
        ]) as $project) {
          $this->processedUrls[] = $project->url;

          // Try to use "publiccode.yaml".
          if (isset($project->files['publiccodeYaml'])) {
            $values = $project->toArray();
            $values['files']['publiccodeYml'] = $values['files']['publiccodeYaml'];
            unset ($values['files']['publiccodeYaml']);
            yield new Project(...$values);
            continue;
          }

          yield $project;
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
   * Gets data.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
   */
  protected function getData(): void {
    $this->fetched = [];
    try {
      $response = $this->httpClient->get(static::ENDPOINT);

      // Fix not valid data.
      $responseData = str_replace('""publiccode_url":', '", "publiccode_url":', (string) $response->getBody());
      $responseData = Json::decode($responseData);
    }
    catch (\Exception $exception) {
      throw new ProviderFetchException(
        $exception->getMessage()
      );
    }

    $this->numberOfProvidedSolutions = count($responseData);
    foreach ($responseData as $row) {
      $required = ['url', 'owner_name', 'name', 'publiccode_url'];
      foreach ($required as $field) {
        if (empty($row[$field])) {
          $this->logger->error('Lacks the mandatory @field in @row', [
            '@field' => $row[$field],
            '@row' => print_r($row, TRUE),
          ]);
          continue 2;
        }
      }

      if (!$api = $this->apiGuesser->getHostingPlatformApi($row['url'])) {
        $this->logger->error('Can\'t determinate hosting platform for @repository repository',
          ['@repository' => $row['url']]);
        continue;
      }

      $this->fetched[$api->baseUrl]['api'] = $api;
      $this->fetched[$api->baseUrl]['projects'][$row['owner_name']][] = $row['name'];
      $this->fetchedUrls[] = $row['url'];
    }
  }

  /**
   * Logs statistics about fetched and processed OSS Solutions.
   */
  protected function logResults(): void {
    if ($unProcessed = array_diff($this->fetchedUrls, $this->processedUrls)) {
      $this->logger->warning('Processed @processed out of @fetched fetched solutions from Dutch catalogue. The following @unprocessed solutions failed: ' . implode(', ', $unProcessed), [
        '@processed' => count($this->processedUrls),
        '@fetched' => $this->numberOfProvidedSolutions,
        '@unprocessed' => count($unProcessed),
        'list' => $unProcessed,
      ]);
    }
    else {
      $this->logger->info('Processed @processed out of @fetched fetched solutions from Dutch catalogue.', [
        '@processed' => count($this->processedUrls),
        '@fetched' => $this->numberOfProvidedSolutions,
      ]);
    }
  }

}
