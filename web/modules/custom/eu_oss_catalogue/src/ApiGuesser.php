<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eu_oss_catalogue\Model\ApiGuess;
use Drupal\eu_oss_catalogue\Model\GitUri;

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
  public function getHostingPlatformApi(string $repositoryUrl): ?ApiGuess {
    if (!UrlHelper::isValid($repositoryUrl)) {
      throw new \InvalidArgumentException(sprintf("The given repository URL parameter is not a valid URL: %s", $repositoryUrl));
    }

    $previousGuess = NULL;
    $guess = NULL;

    if ($gitUri = GitUri::fromUriString($repositoryUrl)) {
      if ($previousGuess = $this->getPreviousGuess($gitUri->host)) {
        return $previousGuess;
      }

      foreach (array_keys($this->hostingPlatformManager->getDefinitions()) as $pluginId) {
        $plugin = $this->hostingPlatformManager->createInstance($pluginId);
        \assert($plugin instanceof EuOssCatalogueHostingPlatformInterface);

        if ($plugin->isHostingPlatformForUrl($repositoryUrl)) {
          $guess = new ApiGuess($pluginId, $plugin->getBaseFromRepoUrl($repositoryUrl));
          break;
        }
      }
    }

    if (!$guess) {
      $this->logger->warning(sprintf("No matching hosting platform plugin was found for URL '%s'", $repositoryUrl));
    }

    if ($guess != $previousGuess) {
      $this->storeGuess($gitUri->host, $guess);
    }

    return $guess;
  }

  /**
   * Returns the previously guesses.
   *
   * @return \Drupal\eu_oss_catalogue\Model\ApiGuess[]
   *   All the previous guesses, keyed by host base URL.
   */
  protected function getPreviousGuesses(): array {
    return $this->state->get(self::STATE_STORAGE_KEY, []);
  }

  /**
   * Returns the previous guess for the specified base URL.
   *
   * @param string $baseUrl
   *   The base URL.
   *
   * @return \Drupal\eu_oss_catalogue\Model\ApiGuess|null
   *   The previous guessed, or NULL if there is no cached result.
   */
  protected function getPreviousGuess(string $baseUrl): ?ApiGuess {
    return $this->getPreviousGuesses()[$baseUrl] ?? NULL;
  }

  /**
   * Stores a guessed plugin ID for the specified base URL.
   *
   * This will override previous entry if any exists.
   *
   * @param string $baseUrl
   *   The base URL.
   * @param \Drupal\eu_oss_catalogue\Model\ApiGuess|null $guess
   *   The guessed value object to store in cache. Not stored if value is empty.
   */
  protected function storeGuess(string $baseUrl, ?ApiGuess $guess): void {
    if ($guess !== NULL) {
      $this->state->set(self::STATE_STORAGE_KEY, [$baseUrl => $guess] + $this->getPreviousGuesses());
    }
  }

}
