<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class DevelopersItaliaFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'imagecache_external',
  ];

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause', 'EUPL-1.2', 'MIT'],
    'oss_category' => [
      'data-analytics',
      'data-collection',
      'digital-citizenship',
      'nothing',
      'website-builder',
    ],
  ];

  /**
   * @covers
   */
  public function testDevelopersItalia(): void {
    $this->fetchSolutions('developers_italia', 4);

    $this->assertOssSolution('GitHub solution 1', 'en');
    $this->assertOssContractor('ACME Ltd');
    $this->assertOssContractor('Das Auto GmbH');
    $this->assertOssContact('Peter Gregory');

    $this->assertOssSolution('Project 1.1', 'it');
    $this->assertOssContact('Big Brother');

    $this->assertOssSolution('Project 1.2', 'de');
    $this->assertOssContact('John Doe');
    $this->assertOssContact('Jane Roe');

    $this->assertOssSolution('Project 1', 'de');

    // Creation log entry for 'GitHub solution 1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:17:"GitHub solution 1";}'));
    // Creation log entry for 'Project 1.1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.1";}'));
    // Creation log entry for 'Project 1.2'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.2";}'));
    // Creation log entry for 'Project 1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:9:"Project 1";}'));

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('testing_provider', 4);

    $this->assertFalse($this->getNodeByTitle('GitHub solution 1'));
    $updatedSolution = $this->getNodeByTitle('GitHub solution 1 (updated)', TRUE);
    $this->assertSame('3.0.0', $updatedSolution->get('oss_software_version')->value);
    $this->assertSame('woI8JkoNfCtzDiDxfpBGrcxTKCyC7BCEcHHgA3ApMxo', $updatedSolution->get('oss_source_hash')->value);
    // Assert that the provider has been added to the list of providers.
    $this->assertSame(2, $updatedSolution->get('oss_source')->count());
    $expected_providers = ['developers_italia', 'testing_provider'];
    $this->assertSame($expected_providers, array_map(fn (array $item): string => $item['plugin_id'], $updatedSolution->get('oss_source')->getValue()));

    // Update log entry for 'GitHub solution 1 (updated)'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was updated.', 'a:1:{s:9:"@solution";s:27:"GitHub solution 1 (updated)";}'));
    // Update log entry for 'Project 1.1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.1";}'));
    // Creation log entry for 'Project 1.2'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.2";}'));
    // Creation log entry for 'Project 1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:9:"Project 1";}'));

    // Ensure logos and screenshots are queued to be downloaded.
    // Check how incoming data has been queued. Only one item is active.
    $imagecacheWarmerQueue = \Drupal::queue('eu_oss_catalogue_imagecache_warmer');
    // The queue should contain 3 logos.
    $this->assertSame(3, $imagecacheWarmerQueue->numberOfItems());
    // "Externals" directory does not exist.
    $this->assertFalse(imagecache_external_get_directory_path());

    // Allow SVGs.
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

    // Queue must be able to process all the items.
    $this->drush('queue:run', ['eu_oss_catalogue_imagecache_warmer']);
    $this->assertSame(0, $imagecacheWarmerQueue->numberOfItems());
    // Downloaded assets:
    // - GitHub solution 1:
    //   - images/logo-solution-1.png,
    // - Project 1.1:
    //   - images/logo-of-project-1-1.svg,
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
        // Project 1.2: logo.
        '3a14aa39b5f6d4fc5e2d52b3a98aecbf.jpg',
      ],
      array_values($cachedImages)
    );
  }

  /**
   * Tests that when a solution is removed upstream, is removed also locally.
   */
  public function testRemovedSolution(): void {
    $this->fetchSolutions('removing_solution_provider', 4);

    $this->assertNotFalse($this->getNodeByTitle('GitHub solution 1'));
    $this->assertNotFalse($this->getNodeByTitle('Project 1.1'));

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('removing_solution_provider', 0);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node1 */
    $this->assertNotFalse($this->getNodeByTitle('GitHub solution 1'));
    $this->assertFalse($this->getNodeByTitle('Project 1.1'));

    // Test that if the solution has more than one provider it is not removed
    // due to a single provider removal.
    $this->fetchSolutions('removing_solution_provider', 1);

    $solution2 = $this->getNodeByTitle('Project 1.1');
    $this->assertNotEmpty($solution2);
    $this->assertSame(1, $solution2->get('oss_source')->count());

    // Import the solutions from developers_italia plugin so that the Solution 2
    // has 2 sources.
    $this->fetchSolutions('developers_italia', 4);

    $solution2 = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertSame(2, $solution2->get('oss_source')->count());

    // Run the removal of the solution again. The solution should not be removed
    // but the source should be removed.
    $this->fetchSolutions('removing_solution_provider', 0);

    $solution2 = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertNotEmpty($solution2);
    $this->assertSame(1, $solution2->get('oss_source')->count());
  }

}
