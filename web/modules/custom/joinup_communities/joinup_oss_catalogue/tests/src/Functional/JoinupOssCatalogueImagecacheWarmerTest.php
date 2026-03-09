<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_oss_catalogue\Functional;

use Drupal\Tests\eu_oss_catalogue\Functional\DevelopersItaliaFetchTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests file cache warming build for Imagecache External.
 */
#[Group('joinup_oss_catalogue')]
#[Group('joinup')]
class JoinupOssCatalogueImagecacheWarmerTest extends DevelopersItaliaFetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'joinup_oss_catalogue',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Allow SVGs in Imagecache External.
    $imagecacheExternalConfig = $this->config('imagecache_external.settings');
    $imagecacheExternalConfig
      ->set(
        'imagecache_external_allowed_mimetypes',
        array_unique(
          array_merge(
            $imagecacheExternalConfig->get('imagecache_external_allowed_mimetypes'),
            ['image/svg+xml']
          )
        )
      )
      ->save();
  }

  /**
   * Tests file cache warming build for Imagecache External.
   */
  public function testDevelopersItalia(): void {
    $this->assertDevelopersItaliaFetch();

    // Ensure logos and screenshots are queued to be downloaded.
    // Check how incoming data has been queued. Only one item is active.
    $imagecacheWarmerQueue = \Drupal::queue('joinup_oss_catalogue_imagecache_warmer');
    // The queue should contain 3 logos and 3 screenshots.
    $this->assertSame(6, $imagecacheWarmerQueue->numberOfItems());
    // "Externals" directory does not exist.
    $this->assertFalse(imagecache_external_get_directory_path());

    // Queue must be able to process all the items.
    $this->drush('queue:run', ['joinup_oss_catalogue_imagecache_warmer']);
    $this->assertSame(0, $imagecacheWarmerQueue->numberOfItems());
    // Downloaded assets:
    // - GitHub solution 1:
    //   - images/logo-solution-1.png,
    //   - Solution_1_EN_Screenshot1.jpg,
    //   - Solution_1_EN_Screenshot2.jpg,
    // - Project 1.1:
    //   - images/logo-of-project-1-1.svg,
    //   - Group_1_Project1_IT_Screenshot.jpg.
    // - Project 1.2:
    //   - https://all.self-hosted.gitlab.example.com/.../avatar/12/logo.jpg.
    $cachedImages = array_diff(
      scandir(imagecache_external_get_directory_path()),
      ['..', '.']
    );
    sort($cachedImages);
    $this->assertEquals(
      [
        // GitHub solution 1: logo.
        '15b4a8a50aee601a2ffe36ebc1c3c892.png',
        // Project 1.1: logo.
        '1a86f60daaaf9df0e07efc994b0d1870.svg',
        // Project 1.1: IT Screenshot.
        '28159bc6540033c5f672f450a908c68d.jpg',
        // Project 1.2: logo.
        '3a14aa39b5f6d4fc5e2d52b3a98aecbf.jpg',
        // GitHub solution 1: EN Screenshot1.
        '4bba7c249bf66c43b239038015aec57f.jpg',
        // GitHub solution 1: EN Screenshot2.
        '89b189f663b5f0fb61c5ca30e4bcd6f3.jpg',
      ],
      array_values($cachedImages)
    );
  }

}
