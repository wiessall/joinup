<?php

declare(strict_types=1);

namespace Drupal\github_feed;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lazy builder rendering GitHub feeds.
 */
class GitHubFeedLazyBuilder {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'cache.render')]
    protected readonly CacheBackendInterface $renderCache,
    protected readonly GitHubFeedInterface $gitHubFeed,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Renders the given GitHub feed.
   *
   * @param string $repositoryUrl
   *   The URL of the repository.
   * @param string $feedType
   *   The type of the feed. Either 'issue' or 'pull_request'.
   * @param int $numberOfResults
   *   The number of results to fetch.
   * @param int $numberOfComments
   *   The number of comments to fetch.
   * @param int $cacheMaxAge
   *   The max age of the rendered output, in seconds.
   *
   * @return array
   *   The renderable array of the GitHub feed.
   */
  #[TrustedCallback]
  public function buildFeed(
    string $repositoryUrl,
    string $feedType,
    int $numberOfResults,
    int $numberOfComments,
    int $cacheMaxAge,
  ): array {
    $feedRenderable = $this->getRenderable($repositoryUrl, $feedType, $numberOfResults, $numberOfComments);

    // We're making this portion cacheable - we only deal with cache max age,
    // otherwise this is safe to be cached - we just don't want the entire page
    // to be re-rendered (in case of logged-in users).
    $feedRenderable['#cache'] = [
      'max-age' => $cacheMaxAge,
      'tags' => ['github_feed'],
    ];
    $feedRenderable['#cache'] += static::getCacheMetadata($repositoryUrl, $feedType, $numberOfResults, $numberOfComments);

    // Needed to tweak (internal) page cache.
    $expires = DateTimePlus::createFromTimestamp(
      $this->time->getRequestTime() + $cacheMaxAge,
      new \DateTimeZone('UTC')
    );
    $feedRenderable['#attached']['http_header'][] = [
      'Expires',
      $expires->format(\DATE_RFC2822),
    ];

    return $feedRenderable;
  }

  /**
   * Returns the renderable array of the feed.
   *
   * @param string $repositoryUrl
   *   The URL of the repository.
   * @param string $feedType
   *   The type of the feed. Either 'issue' or 'pull_request'.
   * @param int $numberOfResults
   *   The number of results to fetch.
   * @param int $numberOfComments
   *   The number of comments to fetch.
   *
   * @return array
   *   The renderable array of the GitHub feed.
   */
  protected function getRenderable(
    string $repositoryUrl,
    string $feedType,
    int $numberOfResults,
    int $numberOfComments,
  ): array {
    $fetchedItems = filter_var($repositoryUrl, FILTER_VALIDATE_URL)
      ? $this->gitHubFeed->fetchItems($repositoryUrl, $feedType, $numberOfResults, $numberOfComments)
      : NULL;

    if ($fetchedItems === NULL) {
      // Render generic failure message.
      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'github-feed-error',
          ],
        ],
        'message' => [
          '#markup' => $this->t('The feed is temporarily unavailable.'),
        ],
      ];
    }

    $element = [
      '#theme' => 'github_feed',
      '#url' => $repositoryUrl,
      '#feed_type' => $feedType,
    ];

    foreach ($fetchedItems as $fetchedItem) {
      $element['#items'][] = $this->getRenderableItem($feedType, $fetchedItem);
    }
    return $element;
  }

  /**
   * Returns the render array of a single item.
   *
   * @param string $type
   *   The fetched item type.
   * @param array $fetchedItem
   *   The fetched item.
   *
   * @return array
   *   The renderable container.
   */
  protected function getRenderableItem(string $type, array $fetchedItem): array {
    $container = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['github-feed-item'],
      ],
    ];

    $container[$type] = [
      '#theme' => 'github_feed_' . $type,
      '#item' => $fetchedItem,
    ];

    if (!empty($fetchedItem['comments'])) {
      $commentItems = array_map(
        fn ($comment): array => [
          '#theme' => 'github_feed_comment',
          '#item' => $comment,
        ],
        $fetchedItem['comments'],
      );

      $container['comments'] = [
        '#theme' => 'github_feed_comments',
        '#attributes' => [
          'class' => ['github-feed-comments'],
        ],
        '#title' => [
          '#markup' => $this->t('Comments'),
        ],
        '#comments' => [
          '#theme' => 'item_list',
          '#list_type' => 'ol',
          '#items' => $commentItems,
        ],
      ];
    }
    return $container;
  }

  /**
   * Returns the render cache metadata for the given GitHub feed.
   *
   * @param string $repositoryUrl
   *   The URL of the repository.
   * @param string $feedType
   *   The type of the feed. One of the items defined in
   *   \Drupal\github_feed\Plugin\Field\FieldType\GitHubFeedItem::allowedTypeValues.
   * @param int $numberOfResults
   *   The number of results to return.
   * @param int $numberOfComments
   *   The number of comments to return.
   *
   * @return array
   *   The cache ID components at the "keys" key, and the cache bin at the "bin"
   *   key. Bin is hard-coded and was added to the return only to centalize
   *   metadata calculation.
   */
  public static function getCacheMetadata(
    string $repositoryUrl,
    string $feedType,
    int $numberOfResults,
    int $numberOfComments,
  ): array {
    return [
      'keys' => [
        'github_feed',
        'view',
        preg_replace('/\W+/', '-', $repositoryUrl),
        $feedType,
        $numberOfResults,
        $numberOfComments,
      ],
      'bin' => 'render',
    ];
  }

}
