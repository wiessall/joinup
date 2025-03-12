<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\ApiGuesserInterface;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueProvider;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Exception\ProviderFetchException;
use Drupal\eu_oss_catalogue\Model\Project;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * French catalogue provider.
 */
#[EuOssCatalogueProvider(
  id: "awesome_code_gouv_fr",
  label: new TranslatableMarkup("Awesome CodeGouvFr"),
  parser: "publiccode_yml",
  type: CatalogueType::National,
  weblinks: [
    [
      'url' => 'https://code.gouv.fr/sources/#/awesome',
      'title' => NULL,
    ],
  ],
  country: "FR",
  description: new TranslatableMarkup("CodeGouvFr, the French National Catalogue with mission to use and contribute to free software and digital commons provides. Awesome Open Source projects actively maintained and funded by Public Administrations and Public Sector organizations."),
)]
class FrenchCatalogue extends EuOssCatalogueProviderPluginBase {

  /**
   * French catalogue endpoint.
   */
  protected const string ENDPOINT = 'https://code.gouv.fr/data/awesome-codegouvfr.json';

  /**
   * List of projects fetched from French catalogue API.
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
   * YAML content from code.gouv.fr.
   */
  protected array $yamlContent = [];

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
   * Gets data.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
   */
  protected function getData(): void {
    $this->fetched = [];
    try {
      $response = $this->httpClient->get(static::ENDPOINT);
      $responseData = Json::decode((string) $response->getBody());
    }
    catch (\Exception $exception) {
      throw new ProviderFetchException(
        $exception->getMessage()
      );
    }

    $this->numberOfProvidedSolutions = count($responseData);
    $required = ['url', 'name'];
    foreach ($responseData as $row) {
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

      // This snippet copies the URL from $row['url'],
      // strips the base URL, splits the remainder into segments,
      // then uses the last segment as $name and the rest as $namespace.
      $url = preg_replace('/^(.*)$/', '$1', $row['url']);
      $pathParts = explode('/', trim(substr($url, strlen($api->baseUrl)), '/'));
      $name = array_pop($pathParts);
      $namespace = implode('/', $pathParts);

      if (!isset($this->fetched[$api->baseUrl]['api'])) {
        $this->fetched[$api->baseUrl]['api'] = $api;
      }

      $this->fetched[$api->baseUrl]['projects'][$namespace][] = $name;
      $this->fetchedUrls[] = $url;
      $this->yamlContent[$url] = json_encode($row);
    }
  }

  /**
   * Logs statistics about fetched and processed OSS Solutions.
   */
  protected function logResults(): void {
    if ($unProcessed = array_diff($this->fetchedUrls, $this->processedUrls)) {
      $this->logger->warning('Processed @processed out of @fetched fetched solutions from French catalogue. The following @unprocessed solutions failed: ' . implode(', ', $unProcessed), [
        '@processed' => count($this->processedUrls),
        '@fetched' => $this->numberOfProvidedSolutions,
        '@unprocessed' => count($unProcessed),
        'list' => $unProcessed,
      ]);
    }
    else {
      $this->logger->info('Processed @processed out of @fetched fetched solutions from French catalogue.', [
        '@processed' => count($this->processedUrls),
        '@fetched' => $this->numberOfProvidedSolutions,
      ]);
    }
  }

}
