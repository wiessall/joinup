<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class FinnishCatalogueFetchTest extends FetchTestBase {

  /**
   * @covers
   */
  public function testImport(): void {
    $this->fetchSolutions('avoinkoodi_fi', 1);
    $this->assertOssSolution('GitHub solution 1', 'en');
  }

}
