<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Behat step definitions for testing OSS solution.
 */
class JoinupOSSSolutionContext extends RawDrupalContext {

  /**
   * OSS search page path.
   */
  protected const string SEARCH_PATH = 'eu-oss-catalogue/solutions';

  /**
   * Navigates to the OSS search page.
   */
  #[When('I visit the OSS search page')]
  public function visitSearchPage(): void {
    $this->visitPath(self::SEARCH_PATH);
  }

  /**
   * Sets validation issues on an oss_solution node identified by title.
   *
   * @param string $title
   *   The node title.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Table with columns: key, description, line, column, type.
   */
  #[Given('the :title oss_solution has the following validation issues:')]
  public function setValidationIssues(string $title, TableNode $table): void {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $title, 'type' => 'oss_solution']);

    if (empty($nodes)) {
      throw new \RuntimeException(sprintf('No oss_solution node with title "%s" found.', $title));
    }

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node */
    $node = reset($nodes);
    $node->set(OssSolutionInterface::PUBLICCODE_VALIDATION_ISSUES_FIELD, $table->getColumnsHash());
    $node->save();
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
