<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use PHPUnit\Framework\Attributes\Group;

/**
 * EU OSS Catalogue test.
 */
#[Group('eu_oss_catalogue')]
class FrenchCatalogueFetchTest extends FetchTestBase {

  public function testImport(): void {
    $this->fetchSolutions('awesome_code_gouv_fr', 1);
    $this->assertOssSolution('GitHub solution 1', 'en');
  }

}
