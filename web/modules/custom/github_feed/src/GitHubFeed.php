<?php

declare(strict_types=1);

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
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClientInterface $client,
    protected LoggerChannelFactoryInterface $logger
  ) {}

  /**
   * {@inheritdoc}
   */
  public function fetchItems(string $url, string $type, int $numberOfResults = 10, int $numberOfComments = 0): ?array {
    [$owner, $project] = $this->getOwnerAndProjectFromUri($url);
    $url = '/repos/' . $owner . '/' . $project . '/' . self::KEYS[$type];
    $url .= '?per_page=' . $numberOfResults . '&page=1';

    $data = $this->clientDoGet($url);
    if ($data === NULL) {
      return NULL;
    }

    $data = json_decode($data, TRUE);
    $return = [];
    foreach ($data as $returnItem) {
      $returnItem['comments'] = [];
      if ($numberOfComments > 0) {
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
  protected function getOwnerAndProjectFromUri(string $uri): array {
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

    $data = $this->clientDoGet($url);
    return $data === NULL ? [] : json_decode($data, TRUE);
  }

  /**
   * Returns the request options.
   *
   * @return array
   *   The request options.
   */
  protected function getRequestHeaders(): array {
    $token = $this->configFactory->get('github_feed.settings')->get('token');
    return empty($token) ? [] : [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
      ],
    ];
  }

  /**
   * Performs a GET request to the GitHub API and catches any exceptions.
   *
   * @param string $url
   *   The URL to request. The URL must not contain the base URL.
   *
   * @return string|null
   *   The response body or NULL if the request failed.
   */
  protected function clientDoGet(string $url): ?string {
    try {
      $response = $this->client->get(
        self::GITHUB_API_URL . $url,
        // 'http_errors' => FALSE is needed to prevent the HTTP client from
        // throwing an exception when the request does not return a 200
        // response. Exceptions break AJAX requests even if they are caught.
        $this->getRequestHeaders() + ['http_errors' => FALSE],
      );
    }
    catch (\Exception $e) {
      // Set this as warnings only. The failures can occur mainly due to the
      // lack/expiration of tokens, thus, we don't need to pollute the logs
      // with errors.
      $this->logger->get('github_feed')->warning($e->getMessage());
      return NULL;
    }

    if ($response->getStatusCode() !== 200) {
      $this->logger->get('github_feed')->warning('The request to %url returned a %code response and "%message" message.', [
        '%url' => $url,
        '%code' => $response->getStatusCode(),
        '%message' => $response->getReasonPhrase() . ' (' . $response->getBody() . ')',
      ]);
      return NULL;
    }

    return $response->getBody()->getContents();
  }

}
