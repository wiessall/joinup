<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Behat step definitions for testing OSS solution.
 */
class JoinupOSSSolutionContext extends RawDrupalContext {

  protected const SEARCH_PATH = 'eu-oss-catalogue/search';

  /**
   * Navigates to the OSS search page.
   *
   * @When I visit the OSS search page
   */
  public function visitSearchPage(): void {
    $this->visitPath(self::SEARCH_PATH);
  }

}
