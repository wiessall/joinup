<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup_test\Plugin\ServiceMock\EuOssEmptyMock;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for testing OSS Catalogue.
 */
class JoinupOssCatalogueContext extends RawDrupalContext {

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

  /**
   * Enables OSS Catalogue service mock.
   *
   * @BeforeScenario @eu_oss_mock&&@api
   *
   * @see \Drupal\joinup_test\Plugin\ServiceMock\EuOssEmptyMock
   */
  public function enableGitHubRequestMock(): void {
    \Drupal::state()->set(EuOssEmptyMock::OSS_CATALOGUE_MOCK_STATE_KEY, TRUE);
  }

  /**
   * Disables SS Catalogue service mock.
   *
   * @AfterScenario @eu_oss_mock&&@api
   *
   * @see \Drupal\joinup_test\Plugin\ServiceMock\EuOssEmptyMock
   */
  public function disableGitHubRequestMock(): void {
    \Drupal::state()->delete(EuOssEmptyMock::OSS_CATALOGUE_MOCK_STATE_KEY);
  }

  /**
   * Asserts the number of times a cron job has run.
   *
   * @param string $job_name
   *   The name of the job.
   * @param int $expected_runs
   *   The expected number of runs.
   *
   * @Then the :job_name job has run :expected_runs times
   */
  public function assertNumberOfCronJobRuns(string $job_name, int $expected_runs): void {
    $search_text = 'Cron ' . $job_name . ' ran successfully.';
    $count = \Drupal::database()->select('watchdog', 'wd')
      ->condition('type', 'eu_oss_catalogue')
      ->condition('message', '%' . $search_text . '%', 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();
    Assert::assertEquals($expected_runs, $count);
  }

  /**
   * Sets the last run time of eu_oss_catalogue cron.
   *
   * @param string $last_run_time
   *   The last run time of the job.
   *
   * @Given the eu_oss_catalogue_cron job was last run (at ):last_run_time
   */
  public function setCronJobRunLastTime(string $last_run_time): void {
    $last_run_time = strtotime($last_run_time);
    \assert($last_run_time, 'The last run time must be a string valid for strtotime().');
    \Drupal::state()->set('eu_oss_catalogue.last_check', $last_run_time);
  }

}
