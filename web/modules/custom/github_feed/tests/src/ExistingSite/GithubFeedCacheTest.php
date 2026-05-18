<?php

declare(strict_types=1);

namespace Drupal\Tests\github_feed\ExistingSite;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\joinup_test\Traits\ParagraphTrait;
use Drupal\joinup_test\Plugin\ServiceMock\GitHubCom;
use Drupal\joinup_test\Traits\GitHubApiMockTrait;
use Drupal\joinup_test_time\Traits\TestTimeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests caching of rendered GitHub feeds.
 *
 * @see \Drupal\github_feed\Plugin\Field\FieldFormatter\GitHubFeedDefaultFormatter
 * @see \Drupal\github_feed\GitHubFeedLazyBuilder
 * @see \Drupal\github_feed\GitHubFeed
 */
#[Group('github_feed')]
class GithubFeedCacheTest extends JoinupExistingSiteTestBase {

  use GitHubApiMockTrait;
  use TestTimeTrait;
  use ParagraphTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    \Drupal::state()->set(GitHubCom::MOCK_STATUS_STATES_KEY, TRUE);
    \Drupal::service('module_installer')->install(['joinup_test_time']);
  }

  /**
   * Tests how rendered GitHub feeds and their page is cached.
   */
  #[DataProvider('providerTestCaching')]
  public function testCaching(string $repositoryUrl): void {
    $nodeWithGitHubFeed = $this->createNode([
      'type' => 'custom_page',
      'title' => $this->randomString(),
      'field_state' => 'published',
    ]);

    $this->appendParagraphToEntityLayout(
      $nodeWithGitHubFeed,
      'field_paragraphs_body',
      [
        'type' => 'github_feed',
        'feed' => [
          'repository' => $repositoryUrl,
          'type' => 'issue',
          'item_num' => 5,
          'comment_num' => 0,
        ],
      ],
    );
    // Save the node again.
    $nodeWithGitHubFeed->save();

    // Anonymous page cache must have a record for this page; containing a
    // valid expiry timestamp (something else than -1).
    $this->drupalGet($nodeWithGitHubFeed->toUrl());
    // First response was a cache miss.
    $this->assertSame('MISS', $this->getSession()->getResponseHeader('x-drupal-cache'));

    // Second is a cache hit!
    $this->drupalGet($nodeWithGitHubFeed->toUrl());
    $this->assertSame('HIT', $this->getSession()->getResponseHeader('x-drupal-cache'));

    $expire = $this->getEntityCanonicalExpiry($nodeWithGitHubFeed);
    // Expiry timestamp must be greater than the initial render timestamp
    // exactly by the max age setting of the
    // "field.field.paragraph.github_feed.feed" field.
    // Unfortunately, we cannot fully mock time since PageCache uses request
    // constants.
    // @todo Move to formatter settings since this doesn't have anything to do
    //   to the field.
    $this->assertGreaterThan(0, $expire);

    // Fetching the page after the cache expiry timestamps means we re-render
    // the page.
    $this->setMockedTime($expire + 1);
    $this->drupalGet($nodeWithGitHubFeed->toUrl());
    $this->assertSame('MISS', $this->getSession()->getResponseHeader('x-drupal-cache'));
    $newExpiry = $this->getEntityCanonicalExpiry($nodeWithGitHubFeed);
    $this->assertGreaterThan($expire, $newExpiry);
  }

  /**
   * Test data provider for ::testCaching.
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerTestCaching(): array {
    return [
      'Unfetchable repo' => [
        'repositoryUrl' => 'https://github.com/namespace/package',
      ],
      // This repository has request mocks.
      'Mocked OE Bootstrap' => [
        'repositoryUrl' => 'https://github.com/openeuropa/oe_bootstrap_theme.git',
      ],
    ];
  }

  /**
   * Gets the expiration of the cached object of the entity's canonical URL.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return int|null
   *   The expiration timestamp of the cached object.
   */
  protected function getEntityCanonicalExpiry(ContentEntityInterface $entity): ?int {
    $cid = $this->getEntityCanonicalPageCacheCid($entity);
    $pageCache = \Drupal::cache('page');
    $cachedData = $pageCache->get($cid);
    return isset($cachedData->expire) ? (int) $cachedData->expire : NULL;
  }

  /**
   * Gets the page cache ID of the canonical HTML page of the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The node.
   *
   * @return string
   *   The cache ID of the canonical page of the node.
   */
  protected function getEntityCanonicalPageCacheCid(ContentEntityInterface $entity): string {
    // Once a cache ID is determined for the request, reuse it for the duration
    // of the request. This ensures that when the cache is written, it is only
    // keyed on request data that was available when it was read. For example,
    // the request format might be NULL during cache lookup and then set during
    // routing, in which case we want to key on NULL during writing, since that
    // will be the value during lookups for subsequent requests.
    return $this->baseUrl . $entity->toUrl()->toString() . ':';
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    $this->restoreGitHubApiRequestMock();
    $this->restoreOriginalTime();
    \Drupal::service('module_installer')->uninstall(['joinup_test_time']);

    // Invalidate the github_feed tag too. This makes this test re-runnable.
    Cache::invalidateTags(['github_feed']);
    parent::tearDown();
  }

}
