<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Hook\BeforeScenario;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Behat step definitions for testing OSS solution.
 */
class JoinupOSSSolutionContext extends RawDrupalContext {

  /**
   * OSS search page path.
   */
  protected const SEARCH_PATH = 'eu-oss-catalogue/solutions';

  /**
   * Navigates to the OSS search page.
   */
  #[When('I visit the OSS search page')]
  public function visitSearchPage(): void {
    $this->visitPath(self::SEARCH_PATH);
  }

  /**
   * Ensures that we do not import solutions when the cron job is performed.
   */
  #[BeforeScenario('@api')]
  public static function disableOssCron(): void {
    $state = \Drupal::state();
    $state->set('eu_oss_catalogue.last_check', strtotime('- 1 hour'));
  }

}
