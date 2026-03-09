<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests EU branding elements.
 */
#[Group('joinup_core')]
class EuBrandingTest extends JoinupExistingSiteTestBase {

  /**
   * Tests that EU webtools global banner appears on all pages.
   */
  public function testEuGlobalBanner(): void {
    // Random picked up pages.
    $pages = [
      '<front>',
      '/contact',
      '/search',
      '/collections',
      '/solutions',
      '/keep-up-to-date',
    ];
    foreach ($pages as $page) {
      $this->drupalGet($page);
      $this->assertSession()->responseContains('<script src="https://webtools.europa.eu/load.js" defer></script>');
    }
  }

}
