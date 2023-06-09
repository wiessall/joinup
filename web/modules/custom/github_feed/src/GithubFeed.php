<?php

declare(strict_types = 1);

namespace Drupal\github_feed;

use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;

/**
 * Fetches feeds from GitHub.
 */
class GithubFeed implements GithubFeedInterface {

  /**
   * Constructs a new GithubFeed object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle client.
   */
  public function __construct(protected CacheBackendInterface $cache, protected ClientInterface $client) {}

  /**
   * {@inheritdoc}
   */
  public function fetchItems(string $url, string $type, string $token, int $numberOfResults = 10, $includeComments = FALSE, int $numberOfComments = 0, bool $resetCache = FALSE): array {
    [$owner, $project] = static::getOwnerAndProjectFromUri($url);
    $cacheId = $owner . ':' . $project . ':' . $type . ':' . $numberOfResults . ':' . $includeComments . ':' . $numberOfComments;
    $cache = $resetCache && $this->cache->get($cacheId);
    if (!$cache) {
      $items = $this->doFetchItems($owner, $project, $type, $token, $numberOfResults, $includeComments, $numberOfComments);
      $this->cache->set($cacheId, $items);
      $cache = $this->cache->get($cacheId);
    }
    return $cache->data;
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
   *   The type of the feed. Either 'issue' or 'pull_request' or 'events'.
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
    return $owner . ':' . $project . ':' . $type . ':' . $numberOfResults . ':' . $includeComments . ':' . $numberOfComments;
  }

  /**
   * Generates a cache ID for the given URL and parameters.
   *
   * @param string $url
   *   The repository URL.
   * @param string $type
   *   The type of the feed. One of the items defined in
   *   \Drupal\github_feed\Plugin\Field\FieldType\GithubFeedItem::allowedTypeValues.
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
   * Fetches the Github issues feed and returns them as an array.
   *
   * @param string $owner
   *   The project owner (user or organization).
   * @param string $project
   *   The project name.
   * @param string $type
   *   The type of the feed. Either 'issue' or 'pull_request'.
   * @param string $token
   *   The GitHub token.
   * @param int $itemNum
   *   The number of items to return.
   * @param bool $includeComments
   *   Whether to include comments or not. Defaults to FALSE.
   * @param int $commentNum
   *   The number of comments to return. Defaults to 0.
   *
   * @return array
   *   An array containing the items.
   */
  protected function doFetchItems(string $owner, string $project, string $type, string $token, int $itemNum, bool $includeComments, int $commentNum): array {
    $url = '/repos/' . $owner . '/' . $project . '/' . self::KEYS[$type];
    $url .= '?per_page=' . $itemNum . '&page=1';

    $response = $this->client->get(self::GITHUB_API_URL . $url, $this->getRequestOptions($token));
    if ($response->getStatusCode() !== 200) {
      if (\Drupal::currentUser()->hasPermission('view invalid api key message')) {
        \Drupal::messenger()->addMessage('An error has occurred while trying to fetch data from GitHub. Error is .', 'error');
      }
      return [];
    }

    $data = json_decode($response->getBody()->getContents(), TRUE);

    $return = [];
    foreach ($data as $returnItem) {
      $returnItem['comments'] = [];
      if ($includeComments) {
        $returnItem['comments'] = $this->fetchItemComments($owner, $project, $type, $token, $returnItem['number'], $commentNum);
      }
      $return[] = $returnItem;
    }

    return $return;
  }

  /**
   * Fetches the Github item comments feed and returns them as an array.
   *
   * @param string $owner
   *   The project owner (user or organization).
   * @param string $project
   *   The project name.
   * @param string $type
   *   The type of the feed. Either 'issue' or 'pull_request'.
   * @param string $token
   *   The GitHub token.
   * @param int $itemNumber
   *   The issue/pull request number ID.
   * @param int $commentNum
   *   The number of comments to return.
   *
   * @return array
   *   An array containing the items.
   */
  protected function fetchItemComments(string $owner, string $project, string $type, string $token, int $itemNumber, int $commentNum): array {
    $url = '/repos/' . $owner . '/' . $project . '/' . self::KEYS[$type] . '/' . $itemNumber . '/comments';
    $url .= '?per_page=' . $commentNum;
    // Default settings.
    $url .= '&sort=created&direction=desc&page=1';
    $response = $this->client->get(self::GITHUB_API_URL . $url, $this->getRequestOptions($token));
    return json_decode($response->getBody()->getContents(), TRUE);
  }

  /**
   * Returns the request options.
   *
   * @param string $token
   *   The GitHub token.
   *
   * @return array
   *   The request options.
   */
  protected function getRequestOptions(string $token = ''): array {
    return empty($token) ? [] : [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
      ],
    ];
  }

}
