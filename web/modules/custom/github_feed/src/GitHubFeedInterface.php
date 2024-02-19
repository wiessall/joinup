<?php

declare(strict_types=1);

namespace Drupal\github_feed;

/**
 * Interface for the github_feed.github_feed service.
 */
interface GitHubFeedInterface {

  /**
   * The GitHub API URL.
   */
  const GITHUB_API_URL = 'https://api.github.com';

  /**
   * The GitHub API URL key for issues.
   */
  const KEYS = [
    'issue' => 'issues',
    'pull_request' => 'pulls',
    'events' => 'events',
  ];

  /**
   * Fetches a list of items from a repository.
   *
   * @param string $url
   *   The URL of the repository.
   * @param string $type
   *   The type of the feed. Either 'issue' or 'pull_request'.
   * @param int $numberOfResults
   *   The number of results to fetch.
   * @param int $numberOfComments
   *   The number of comments to fetch.
   *
   * @return array|null
   *   The list of items or NULL if the request failed.
   */
  public function fetchItems(string $url, string $type, int $numberOfResults = 10, int $numberOfComments = 0): ?array;

}
