<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class DutchCatalogueFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later'],
    'oss_category' => ['digital-citizenship'],
  ];

  /**
   * @covers
   */
  public function testImport(): void {
    $this->fetchSolutions('dutch_catalogue', 3);

    $this->assertOssSolution('GitHub solution 1', 'en');
    $this->assertOssSolution('Project 1.1', 'it');
    $this->assertOssSolution('Project 1.2', 'de');
  }

}
