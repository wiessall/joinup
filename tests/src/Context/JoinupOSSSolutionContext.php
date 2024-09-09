<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Behat step definitions for testing OSS solution.
 */
class JoinupOSSSolutionContext extends RawDrupalContext {

  /**
   * OSS welcome page path.
   */
  protected const WELCOME_PATH = 'eu-oss-catalogue';

  /**
   * OSS search page path.
   */
  protected const SEARCH_PATH = 'eu-oss-catalogue/solutions';

  /**
   * Navigates to the OSS search page.
   *
   * @When I visit the OSS search page
   */
  public function visitSearchPage(): void {
    $this->visitPath(self::SEARCH_PATH);
  }

  /**
   * Navigates to the OSS welcome page.
   *
   * @When I visit the OSS welcome page
   */
  public function visitWelcomePage(): void {
    $this->visitPath(self::WELCOME_PATH);
  }

  /**
   * Ensures that we do not import solutions when the cron job is performed.
   *
   * @BeforeScenario @api
   */
  public static function disableOssCron(): void {
    $state = \Drupal::state();
    $state->set('eu_oss_catalogue.last_check', strtotime('- 1 hour'));
  }

  /**
   * Enables EU OSS Catalogue functionality.
   *
   * @BeforeScenario @api&&@eu-oss-catalogue
   *
   * @todo Remove this method and all @eu-oss-catalogue tags in ISAICP-9053.
   */
  public function enableEuOssCatalogue(): void {
    joinup_oss_catalogue_toggle('enable');
  }

  /**
   * Disables EU OSS Catalogue functionality.
   *
   * @AfterScenario @api&&@eu-oss-catalogue
   *
   * @todo Remove this method and all @eu-oss-catalogue tags in ISAICP-9053.
   */
  public function disableEuOssCatalogue(): void {
    joinup_oss_catalogue_toggle('disable');
  }

}
