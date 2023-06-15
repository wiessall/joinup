<?php

declare(strict_types = 1);

namespace Drupal\github_feed;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Fetches feeds from GitHub.
 */
class GitHubFeed implements GitHubFeedInterface {

  /**
   * Constructs a new GitHubFeed object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory, protected ClientInterface $client, protected LoggerChannelFactoryInterface $logger) {}

  /**
   * {@inheritdoc}
   */
  public function fetchItems(string $url, string $type, int $numberOfResults = 10, $includeComments = FALSE, int $numberOfComments = 0, bool $resetCache = FALSE): ?array {
    [$owner, $project] = static::getOwnerAndProjectFromUri($url);
    $url = '/repos/' . $owner . '/' . $project . '/' . self::KEYS[$type];
    $url .= '?per_page=' . $numberOfResults . '&page=1';

    try {
      $response = $this->client->get(self::GITHUB_API_URL . $url, $this->getRequestHeaders());
    }
    catch (\Exception $e) {
      // Set this as warnings only. The failures can occur mainly due to the
      // lack/expiration of tokens, thus, we don't need to pollute the logs
      // with errors.
      $this->logger->get('github_feed')->warning($e->getMessage());
      return NULL;
    }

    $data = json_decode($response->getBody()->getContents(), TRUE);
    $return = [];
    foreach ($data as $returnItem) {
      $returnItem['comments'] = [];
      if ($includeComments) {
        $returnItem['comments'] = $this->fetchItemComments($owner, $project, $type, $returnItem['number'], $numberOfComments);
      }
      $return[] = $returnItem;
    }

    return $return;
  }

  /**
   * Returns the owner and the project of a GitHub URI.
   *
   * @param string $uri
   *   The URI to parse.
   *
   * @return array
   *   An array containing the owner and the project.
   */
  public static function getOwnerAndProjectFromUri(string $uri): array {
    $parsedUrl = parse_url($uri);
    $uri = $parsedUrl['path'];

    $owner = '';
    $project = '';
    $uri_parts = explode('/', $uri);
    if (count($uri_parts) > 1) {
      $owner = $uri_parts[count($uri_parts) - 2];
      $project = $uri_parts[count($uri_parts) - 1];
      // Remove the .git extension.
      $project = str_replace('.git', '', $project);
    }
    return [$owner, $project];
  }

  /**
   * Generates a cache ID for the given parameters.
   *
   * @param string $owner
   *   The owner of the project.
   * @param string $project
   *   The project name.
   * @param string $type
   *   The type of the feed. One of the items defined in
   *   \Drupal\github_feed\Plugin\Field\FieldType\GitHubFeedItem::allowedTypeValues.
   * @param int $numberOfResults
   *   The number of results to return.
   * @param bool $includeComments
   *   Whether to include comments or not.
   * @param int $numberOfComments
   *   The number of comments to return.
   *
   * @return string
   *   The cache ID.
   */
  public static function generateCacheId(string $owner, string $project, string $type, int $numberOfResults = 10, bool $includeComments = FALSE, int $numberOfComments = 0): string {
    return $owner . ':' . $project . ':' . $type . ':' . $numberOfResults . ':' . (int) $includeComments . ':' . $numberOfComments;
  }

  /**
   * Generates a cache ID for the given URL and parameters.
   *
   * @param string $url
   *   The repository URL.
   * @param string $type
   *   The type of the feed. One of the items defined in
   *   \Drupal\github_feed\Plugin\Field\FieldType\GitHubFeedItem::allowedTypeValues.
   * @param int $numberOfResults
   *   The number of results to return.
   * @param bool $includeComments
   *   Whether to include comments or not.
   * @param int $numberOfComments
   *   The number of comments to return.
   *
   * @return string
   *   The cache ID.
   */
  public static function generateCacheIdFromUrlAndData(string $url, string $type, int $numberOfResults = 10, bool $includeComments = FALSE, int $numberOfComments = 0): string {
    [$owner, $project] = static::getOwnerAndProjectFromUri($url);
    return static::generateCacheId($owner, $project, $type, $numberOfResults, $includeComments, $numberOfComments);
  }

  /**
   * Fetches the GitHub item comments feed and returns them as an array.
   *
   * @param string $owner
   *   The project owner (user or organization).
   * @param string $project
   *   The project name.
   * @param string $type
   *   The type of the feed. Either 'issue' or 'pull_request'.
   * @param int $itemNumber
   *   The issue/pull request number ID.
   * @param int $commentNum
   *   The number of comments to return.
   *
   * @return array
   *   An array containing the items.
   */
  protected function fetchItemComments(string $owner, string $project, string $type, int $itemNumber, int $commentNum): array {
    $url = '/repos/' . $owner . '/' . $project . '/' . self::KEYS[$type] . '/' . $itemNumber . '/comments';
    $url .= '?per_page=' . $commentNum;
    // Default settings.
    $url .= '&sort=created&direction=desc&page=1';
    try {
      $response = $this->client->get(self::GITHUB_API_URL . $url, $this->getRequestHeaders());
    }
    catch (\Exception $e) {
      // Set this as warnings only. The failures can occur mainly due to the
      // lack/expiration of tokens, thus, we don't need to pollute the logs
      // with errors.
      $this->logger->get('github_feed')->warning($e->getMessage());
      return [];
    }

    return json_decode($response->getBody()->getContents(), TRUE);
  }

  /**
   * Returns the request options.
   *
   * @return array
   *   The request options.
   */
  protected function getRequestHeaders(): array {
    $token = $this->getGitHubAccessToken();
    return empty($token) ? [] : [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getGitHubAccessToken(): string {
    return $this->configFactory->get('github_feed.settings')->get('token');
  }

}
