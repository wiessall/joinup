<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Converts a path relative to solution code repository to an absolute URL.
 *
 * @EuOssCatalogueResolver(
 *   id = "absolute_url",
 *   label = "Absolute URL",
 * )
 */
class AbsoluteUrlResolver extends DefaultResolver {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManger
   *   The language manager service.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $platformManager
   *   The EU OSS Catalogue code hosting platform plugin manager.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelInterface $logger,
    protected LanguageManagerInterface $languageManger,
    protected EuOssCataloguePluginManagerInterface $platformManager,
    protected ClientInterface $httpClient,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $this->languageManger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('language_manager'),
      $container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['verify' => FALSE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    parent::resolve($value, $project, $metadata, $fieldItemList);
    if (!$value) {
      return;
    }

    $platformBaseUrl = $project->getHostingPlatformUrl();
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $platform */
    $platform = $this->platformManager->createInstance($project->platform, [
      'baseUrl' => $platformBaseUrl,
    ]);

    $urls = [];
    foreach ($value as $langcode => $paths) {
      foreach ($paths as $path) {
        if ($absoluteUrl = $this->getAbsoluteUrl($project, $platform, $path)) {
          $urls[$langcode][] = $absoluteUrl;
        }
      }
    }

    $value = $urls;
  }

  /**
   * Returns the absolute URL given a file path.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $platform
   *   The code hosting platform plugin instance.
   * @param string $path
   *   The relative or absolute path to file.
   *
   * @return string|null
   *   The absolute URL or NULL if it cannot be resolved.
   */
  protected function getAbsoluteUrl(Project $project, EuOssCatalogueHostingPlatformInterface $platform, string $path): ?string {
    if (!$path = trim($path)) {
      // Empty path.
      return NULL;
    }
    if (!$url = $platform->getFileRawUrl($path, $project->url)) {
      // Cannot get the file's raw URL.
      return NULL;
    }

    // HTTP verification is required.
    if ($this->getConfiguration()['verify']) {
      try {
        $options = [RequestOptions::TIMEOUT => 10];
        $this->httpClient->request('HEAD', $url, $options);
      }
      catch (\Throwable $exception) {
        // Just log a warning and continue.
        Error::logException($this->logger, $exception, Error::DEFAULT_ERROR_MESSAGE, [], LogLevel::WARNING);
        return NULL;
      }
    }

    return $url;
  }

}
