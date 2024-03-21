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
   * Deletes an OSS Solution.
   *
   * @param string $title
   *   The entity title.
   *
   * @When I delete the :title oss_solution
   */
  public function deleteOssSolution(string $title): void {
    $this->getEntityByLabel('node', $title, 'oss_solution')->delete();
  }

}
