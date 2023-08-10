<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_platform_github\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Cache\Adapter\DrupalAdapter;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Site\Settings;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\HostingPlatformResultInterface;
use Drupal\eu_oss_catalogue_platform_github\GitHubResult;
use Drupal\multivalue_form_element\Element\MultiValue;
use Github\AuthMethod;
use Github\Client;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Git code hosting platform for github.com.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "github",
 *   label = @Translation("GitHub"),
 * )
 */
class GitHub extends EuOssCatalogueHostingPlatformPluginBase implements PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Static cache for the GitHub client.
   */
  protected Client $client;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin ID.
   * @param array $pluginDefinition
   *   The plugin definition.
   * @param \Psr\Http\Client\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal settings.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected ClientInterface $httpClient,
    protected Settings $settings,
    protected CacheBackendInterface $cache,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginFefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginFefinition,
      $container->get('http_client'),
      $container->get('settings'),
      $container->get('cache.eu_oss_catalogue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'projects' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $form['projects'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('Projects'),
      '#description' => $this->t('If name is omitted, all projects from that vendor are eligible.'),
      '#default_value' => $this->getConfiguration()['projects'],
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#required' => TRUE,
      'vendor' => [
        '#type' => 'textfield',
        '#title' => $this->t('Vendor'),
        '#required' => TRUE,
      ],
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    $projects = [];
    foreach ($formState->getValue('projects') as $delta => $project) {
      $vendor = trim($project['vendor']);
      $name = trim($project['name']);

      try {
        if (empty($name)) {
          $this->getClient()->api('user')->show($vendor);
        }
        else {
          $this->getClient()->api('repository')->show($vendor, $name);
        }
      }
      catch (\RuntimeException $exception) {
        if ($exception->getCode() === 404) {
          $formState->setErrorByName("projects][$delta", $this->t("Vendor or repository %ref doesn't exist on GitHub platform", [
            '%ref' => rtrim("$vendor/$name", '/'),
          ]));
        }
        else {
          throw $exception;
        }
      }
      $projects[] = ['vendor' => $vendor, 'name' => $name];
    }
    $formState->setValue('projects', $projects);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
    $this->configuration['projects'] = $formState->getValue('projects');
  }

  /**
   * {@inheritdoc}
   */
  public function getRepositories(): iterable {
    $repositories = $this->expandRepositories($this->getConfiguration()['projects']);
    foreach ($repositories as $fullName => $data) {
      [$vendor, $name] = explode('/', $fullName);
      yield new GitHubResult($vendor, $name, $data['branch'], $data['reference']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadFile(string $filePath, HostingPlatformResultInterface $result): ?string {
    \assert($result instanceof GitHubResult);
    try {
      return $this->getClient()->api('repo')->contents()->download($result->vendor, $result->name, $filePath, $result->defaultBranch);
    }
    catch (\RuntimeException $exception) {
      if ($exception->getCode() === 404) {
        // No file.
        return NULL;
      }
      throw $exception;
    }
  }

  /**
   * Returns the GitHub client.
   *
   * @return \Github\Client
   *   The GitHub client.
   */
  protected function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = Client::createWithHttpClient($this->httpClient);
      $token = Settings::get('eu_oss_catalogue', [])['platform_github']['access_token'] ?? NULL;
      if (!$token) {
        // @todo Should we show a message to user or it's only developer stuff?
        throw new \InvalidArgumentException('GitHub access token is not set');
      }
      $this->client->authenticate($token, NULL, AuthMethod::ACCESS_TOKEN);

      // Cache requests to GitHub in 'cache.eu_oss_catalogue' bin.
      $adapter = new DrupalAdapter($this->cache, 'github');
      // Results are cached for 1 day (60 x 60 x 24 = 86400).
      $this->client->addCache($adapter, ['default_ttl' => 86400]);
    }
    return $this->client;
  }

  /**
   * Expands the project where only the vendor is specified.
   *
   * @param array[] $projects
   *   Associative array of projects, keyed by $vendor/$name and having the
   *   default branch as values.
   */
  protected function expandRepositories(array $projects): array {
    $repositories = [];

    try {
      foreach ($projects as $project) {
        if (empty($project['name'])) {
          foreach ($this->getClient()->api('user')->repositories($project['vendor']) as $repository) {
            $repositories[$repository['full_name']] = [
              'branch' => $repository['default_branch'],
              'reference' => $repository['id'],
            ];
          }
          continue;
        }
        $repository = $this->getClient()->api('repository')->show($project['vendor'], $project['name']);
        $repositories[$repository['full_name']] = [
          'branch' => $repository['default_branch'],
          'reference' => $repository['id'],
        ];
      }
    }
    catch (\Throwable $exception) {
      // Fail silently.
    }

    return $repositories;
  }

}
