<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class FinnishCatalogueFetchTest extends FetchTestBase {

  public static $modules = [
    // 'eu_oss_catalogue_test_not_allowed_mock',
  ];

  /**
   * Tests fetching from Finnish catalogue.
   */
  public function testImport(): void {
    $this->fetchSolutions('avoinkoodi_fi', 2);
    $this->assertOssSolution('GitHub solution 1', 'en');
    $this->assertOssSolution('GitHub solution 2', 'it');
  }

  /**
   * Tests fetching from Finnish catalogue.
   */
  public function testFetch(): void {
    $this->fetchSolutions('offentligkod_se', 1);
  }

}
