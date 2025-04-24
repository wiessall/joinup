<?php

declare(strict_types=1);

namespace Drupal\github_feed;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Render\RendererInterface;
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
    protected readonly RendererInterface $renderer,
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
  public function renderFeed(
    string $repositoryUrl,
    string $feedType,
    int $numberOfResults,
    int $numberOfComments,
    int $cacheMaxAge,
  ): array {
    if (!filter_var($repositoryUrl, FILTER_VALIDATE_URL)) {
      return [];
    }

    $fetchedItems = $this->gitHubFeed->fetchItems($repositoryUrl, $feedType, $numberOfResults, $numberOfComments);
    if ($fetchedItems === NULL) {
      // Render generic failure message, and skip caching.
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
      '#cache' => ['max-age' => $cacheMaxAge],
    ];

    foreach ($fetchedItems as $fetchedItem) {
      $element['#items'][] = $this->getRenderableItem($feedType, $fetchedItem);
    }

    // Needed to tweak (internal) page cache.
    $now = new DateTimePlus('now', new \DateTimeZone('UTC'));
    $element['#attached']['http_header'][] = [
      'Expires',
      $now->modify("+$cacheMaxAge second")->format(\DATE_RFC2822),
    ];

    // We're making this portion cacheable - we only deal with cache max age,
    // otherwise this is safe to be cached - we just don't want the entire page
    // to be re-rendered.
    $element['#cache']['keys'] = static::getCacheKeys(
      $repositoryUrl,
      $feedType,
      $numberOfResults,
      $numberOfComments,
    );
    $element['#cache']['bin'] = 'render';

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
  public function getRenderableItem(string $type, array $fetchedItem): array {
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
   * Generates a cache ID for the given parameters.
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
   * @return array<string|int>
   *   The cache ID components.
   */
  public static function getCacheKeys(
    string $repositoryUrl,
    string $feedType,
    int $numberOfResults,
    int $numberOfComments,
  ): array {
    return [
      'github_feed',
      'view',
      preg_replace('/\W/', '', $repositoryUrl),
      $feedType,
      $numberOfResults,
      $numberOfComments,
    ];
  }

}
