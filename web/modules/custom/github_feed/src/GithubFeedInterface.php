<?php

declare(strict_types = 1);

namespace Drupal\github_feed;

/**
 * Interface for the github_feed service.
 */
interface GithubFeedInterface {

  /**
   * The Github API URL.
   */
  const GITHUB_API_URL = 'https://api.github.com';

  /**
   * The Github API URL key for issues.
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
   * @param bool $includeComments
   *   Whether to include comments.
   * @param int $numberOfComments
   *   The number of comments to fetch.
   */
  public function fetchItems(string $url, string $type, int $numberOfResults = 10, $includeComments = FALSE, int $numberOfComments = 0): array;

}
