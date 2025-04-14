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
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Avoinkoodi catalogue provider.
 */
#[EuOssCatalogueProvider(
  id: 'avoinkoodi_fi',
  label: new TranslatableMarkup('Avoinkoodi.fi'),
  parser: 'publiccode_yml',
  type: CatalogueType::National,
  weblinks: [
    [
      'url' => 'http://avoinkoodi.fi',
      'title' => NULL,
    ],
  ],
  country: 'FI',
  description: new TranslatableMarkup('Avoinkoodi.fi catalogue lists together software used in Finnish government, education and the local government sector, whose source code has been published openly.'),
)]
class FinnishCatalogue extends EuOssCatalogueProviderPluginBase {

  /**
   * Catalogue endpoints.
   *
   * @var string[]
   */
  protected const array ENDPOINTS = [
    'http://avoinkoodi.fi/projects.json',
    'http://avoinkoodi.fi/municipalityprojects.json',
    'http://avoinkoodi.fi/eduprojects.json',
  ];

  /**
   * List of projects fetched from Dutch catalogue API.
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
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $hostingPlatform */
      $hostingPlatform = $this->platformManager->createInstance($namespaces['api']->pluginId, $platformConfig);

      /** @var \Drupal\eu_oss_catalogue\Model\Project $project */
      try {
        foreach ($hostingPlatform->getProjects(['publiccodeYml' => 'publiccode.yml']) as $project) {
          if (isset($project->files['publiccodeYml'])) {
            yield $project;
          }
        }
      }
      catch (HostingPlatformFetchException $exception) {
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

    foreach (self::ENDPOINTS as $endpointUrl) {
      try {
        $response = $this->httpClient->get($endpointUrl);
        $responseData = Json::decode($response->getBody()->getContents());
        $responseData = reset($responseData);
      }
      catch (\Exception $exception) {
        throw new ProviderFetchException(
          $exception->getMessage()
        );
      }

      $this->processResponseData($responseData);
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
      if (empty($row['code_url'])) {
        $this->logger->error('Lacks the mandatory code_url value in @row', [
          '@row' => print_r($row, TRUE),
        ]);
        continue;
      }

      // Limit log noise.
      if (in_array($row['code_url'], $this->ignoredResources())) {
        continue;
      }

      $parts = explode('/', parse_url($row['code_url'], PHP_URL_PATH));
      if (count($parts) != 3 || !$api = $this->apiGuesser->getHostingPlatformApi($row['code_url'])) {
        continue;
      }

      if (in_array($row['code_url'], $this->fetchedUrls)) {
        continue;
      }

      $this->fetched[$api->baseUrl]['api'] = $api;
      $this->fetched[$api->baseUrl]['projects'][$parts[1]][] = $parts[2];
      $this->fetchedUrls[] = $row['code_url'];
    }
  }

  /**
   * Ignored resources.
   *
   * @return string[]
   *   Ignored URLs.
   */
  protected function ignoredResources(): array {
    return [
      'https://docs.moodle.org/dev/Main_Page',
    ];
  }

}
