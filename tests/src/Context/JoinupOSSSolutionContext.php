<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Behat step definitions for testing OSS solution.
 */
class JoinupOSSSolutionContext extends RawDrupalContext {

  use EntityTrait;

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
   * Toggles ON/OFF EU OSS Catalogue functionality.
   *
   * @param string $toggle
   *   Could be 'enable', 'disable'.
   *
   * @Given I :toggle EU OSS Catalogue
   */
  public function toggleEuOssCatalogue(string $toggle): void {
    assert(in_array($toggle, ['enable', 'disable'], TRUE));

    /** @var \Drupal\joinup_core\KillSwitchInterface $killSwitch */
    $killSwitch = \Drupal::service('joinup_core.private_file_kill_switch');
    $type = 'disable-eu-oss-catalogue';
    if ($toggle === 'enable') {
      $killSwitch->remove($type);
    }
    else {
      $killSwitch->create($type);
    }
    \Drupal::getContainer()->get('cache.page')->deleteAll();
  }

}
