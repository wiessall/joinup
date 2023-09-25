<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use Drupal\eu_oss_catalogue\Model\FetchedSolution;
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
   *   values are associative arrays keyed by the namespace and having each
   *   value an associative array keyed by the project name and the value is the
   *   project data.
   */
  protected array $fetched;

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
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected LoggerChannelInterface $logger,
    protected Client $httpClient,
    protected EuOssCataloguePluginManagerInterface $platformManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $parserManager, $this->logger);
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
      if (!$platformId = $this->guessPlatformPluginId($baseUrl)) {
        continue;
      }

      $projects = array_map(function (array $items): array {
        return array_keys($items);
      }, $namespaces);

      $platformConfig = ['projects' => $projects, 'url' => $baseUrl];
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $hostingPlatform */
      $hostingPlatform = $this->platformManager->createInstance($platformId, $platformConfig);

      unset($this->fetched[$baseUrl]);

      /** @var \Drupal\eu_oss_catalogue\Model\Project $project */
      foreach ($hostingPlatform->getProjects() as $project) {
        $fileBlob = $namespaces[$project->namespace][$project->name]['publiccodeYml'];
        try {
          $data = Yaml::decode($fileBlob);
          yield new FetchedSolution(
            $project->url,
            $data['name'] ?? '',
            $data,
            $namespaces[$project->namespace][$project->name]['id'],
          );
        }
        catch (InvalidDataTypeException $exception) {
          $this->logger->error('Cannot decode publiccode.yml of @url. Message: @message', [
            '@url' => $project->url,
            '@message' => $exception->getMessage(),
          ]);
        }
      }
    }
  }

  /**
   * Fetches data from Developers Italia API endpoint.
   */
  protected function doFetch(): void {
    $next = '';

    do {
      $endpointUrl = static::ENDPOINT . $next . ($next ? '&' : '?') . 'page[size]=100';
      $response = $this->httpClient->get($endpointUrl);
      $read = Json::decode($response->getBody()->getContents());

      foreach ($read['data'] as $newItem) {
        $active = $newItem['active'] ?? FALSE;
        if ($active) {
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

          $this->fetched[$baseUrl][$namespace][$name] = $newItem;
        }
      }

      $next = $read['links']['next'];
    } while ($next !== NULL);
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
