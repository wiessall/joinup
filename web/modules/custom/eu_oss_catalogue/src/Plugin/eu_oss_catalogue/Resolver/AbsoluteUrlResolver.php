<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueResolver;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Converts a path relative to solution code repository to an absolute URL.
 */
#[EuOssCatalogueResolver(
  id: "absolute_url",
  label: new TranslatableMarkup("Absolute URL")
)]
class AbsoluteUrlResolver extends DefaultResolver {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
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

    if (!$this->isSafeUrl($url)) {
      $this->logger->warning('Skipping URL that resolves to a private or reserved address: @url', ['@url' => $url]);
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

  /**
   * Returns whether the URL is safe to request.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if the URL is safe, FALSE otherwise.
   */
  protected function isSafeUrl(string $url): bool {
    $parsed = parse_url($url);
    if (!isset($parsed['scheme'], $parsed['host'])) {
      return FALSE;
    }
    $host = $parsed['host'];
    // Reject common loopback hostnames.
    if (in_array(strtolower($host), ['localhost', 'ip6-localhost', 'ip6-loopback'], TRUE)) {
      return FALSE;
    }
    // Reject literal IP addresses; valid URLs must use domain names.
    if (filter_var($host, FILTER_VALIDATE_IP) !== FALSE) {
      return FALSE;
    }
    return TRUE;
  }

}
