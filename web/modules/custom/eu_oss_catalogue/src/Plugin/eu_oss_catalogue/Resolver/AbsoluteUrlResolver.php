<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
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
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $platformManager
   *   The EU OSS Catalogue code hosting platform plugin manager.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelInterface $logger,
    protected EuOssCataloguePluginManagerInterface $platformManager,
    protected ClientInterface $httpClient,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(Project $project, array $metadata, FieldItemListInterface $fieldItemList): array {
    if (!$paths = parent::resolve($project, $metadata, $fieldItemList)) {
      return [];
    }

    $platformBaseUrl = $project->getHostingPlatformUrl();
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $platform */
    $platform = $this->platformManager->createInstance($project->platform, [
      'baseUrl' => $platformBaseUrl,
    ]);

    $urls = [];
    foreach ($paths as $path) {
      $path = trim($path);
      if ($path && $url = $platform->getFileRawUrl($path, $project->url)) {
        // HTTP verification is required.
        if ($this->getConfiguration()['verify']) {
          try {
            $options = [RequestOptions::TIMEOUT => 10];
            $this->httpClient->request('HEAD', $url, $options);
          }
          catch (\Throwable $exception) {
            // Just log a warning and continue.
            Error::logException($this->logger, $exception, Error::DEFAULT_ERROR_MESSAGE, [], LogLevel::WARNING);
            continue;
          }
        }
        $urls[] = $url;
      }
    }

    return $urls;
  }

}
