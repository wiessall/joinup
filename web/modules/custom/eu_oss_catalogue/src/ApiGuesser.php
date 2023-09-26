<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Psr\Log\LogLevel;

/**
 * Remote git API guesser.
 *
 * @internal
 */
final class ApiGuesser implements ApiGuesserInterface {

  /**
   * Storage key in State API.
   *
   * @const string
   */
  protected const STATE_STORAGE_KEY = 'eu_oss_catalogue.api_guesser.guessed';

  /**
   * Guesser defaults.
   *
   * @const string[]
   */
  protected const GUESSED_DEFAULTS = [
    // Bitbucket is not yet implemented.
    // 'bitbucket.org' => 'bitbucket_org',.
    'github.com' => 'github',
    'gitlab.com' => 'gitlab_com',
  ];

  /**
   * Construct a new ApiGuesser instance.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $hostingPlatformManager
   *   The hosting platform plugin manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel to use.
   */
  public function __construct(
    protected EuOssCataloguePluginManagerInterface $hostingPlatformManager,
    protected StateInterface $state,
    protected LoggerChannelInterface $logger
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getHostingPlatformPluginId(string $repositoryUrl): ?string {
    if (!UrlHelper::isValid($repositoryUrl)) {
      throw new \InvalidArgumentException(
        sprintf(
          "The given repository URL parameter is not a valid URL: %s",
          $repositoryUrl
        )
      );
    }

    $previouslyGuessedPluginId = NULL;
    $guessedPluginId = NULL;

    if (is_a(($gitUri = GitUri::fromUriString($repositoryUrl)), GitUri::class)) {
      if ($previouslyGuessedPluginId = $this->getGuessedPluginId($gitUri->host)) {
        return $previouslyGuessedPluginId;
      }

      foreach (array_keys($this->hostingPlatformManager->getDefinitions()) as $pluginId) {
        $plugin = $this->hostingPlatformManager->createInstance($pluginId);
        assert($plugin instanceof EuOssCatalogueHostingPlatformInterface);

        if ($plugin->isHostingPlatformForUrl($repositoryUrl)) {
          $guessedPluginId = $pluginId;
          break;
        }
      }
    }

    if (!$guessedPluginId) {
      $this->logger->log(
        LogLevel::WARNING,
        sprintf(
          "No matching hosting platform plugin was found for URL '%s'",
          $repositoryUrl
        )
      );
    }

    if ($guessedPluginId !== $previouslyGuessedPluginId) {
      $this->storeGuessedPluginId($gitUri->host, $guessedPluginId);
    }
    return $guessedPluginId;
  }

  /**
   * Returns the previously guessed plugin IDs.
   *
   * @return string[]
   *   All the previously guessed plugin IDs, keyed by host base URL.
   */
  protected function getGuessedPluginIds(): array {
    return $this->state->get(self::STATE_STORAGE_KEY, self::GUESSED_DEFAULTS);
  }

  /**
   * Returns the previously guessed plugin ID for the specified base URL.
   *
   * @param string $baseUrl
   *   The base URL.
   *
   * @return string|null
   *   The previously guessed plugin ID if any, or NULL if there is no cached
   *   result.
   */
  protected function getGuessedPluginId(string $baseUrl): ?string {
    return $this->getGuessedPluginIds()[$baseUrl] ?? NULL;
  }

  /**
   * Stores a guessed plugin ID for the specified base URL.
   *
   * This will override previous entry if any exists.
   *
   * @param string $baseUrl
   *   The base URL.
   * @param string|null $pluginId
   *   The discovered plugin ID to store in cache. Not stored if value is empty.
   */
  protected function storeGuessedPluginId(string $baseUrl, ?string $pluginId): void {
    if (in_array($pluginId, [NULL, ''], TRUE)) {
      return;
    }
    $this->state->set(
      self::STATE_STORAGE_KEY,
      [$baseUrl => $pluginId] + $this->getGuessedPluginIds()
    );
  }

}
