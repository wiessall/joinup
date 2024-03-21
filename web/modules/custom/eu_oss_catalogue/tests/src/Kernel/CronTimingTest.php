<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the cron timing logic.
 *
 * @group eu_oss_catalogue
 */
class CronTimingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eu_oss_catalogue',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('dblog', ['watchdog']);
  }

  /**
   * Tests cron periodical task.
   */
  public function testCronPeriodicalTask(): void {
    $state = \Drupal::state();
    $cron = \Drupal::service('cron');
    $today = strtotime(date('Y-m-d', time()) . ' 2:00:00');

    // Given the "eu_oss_catalogue_cron" job has run 0 times.
    $this->assertEquals(0, $this->getJobRunCount('eu_oss_catalogue_cron'));
    $this->assertEquals(0, $state->get('eu_oss_catalogue.last_check'));

    // When I run cron.
    $cron->run();

    // Then the "eu_oss_catalogue_cron" job has run 1 times.
    $this->assertEquals(1, $this->getJobRunCount('eu_oss_catalogue_cron'));
    $this->assertEquals($today, $state->get('eu_oss_catalogue.last_check'));

    // When I run cron again.
    $cron->run();

    // Then the "eu_oss_catalogue_cron" job has still run only once.
    $this->assertEquals(1, $this->getJobRunCount('eu_oss_catalogue_cron'));
    $this->assertEquals($today, $state->get('eu_oss_catalogue.last_check'));

    // Mock the DrupalDateTime class to return a fixed date in the future.
    $future_timestamp = time() + (2 * 24 * 60 * 60);
    $mocked_time = $this->createMock(TimeInterface::class);
    $mocked_time->method('getRequestTime')->willReturn($future_timestamp);
    // When 1 day and 1 minute passes
    // Replace the real DrupalDateTime class with the mocked one.
    $this->container->set('datetime.time', $mocked_time);

    // And I run cron.
    $cron->run();

    $this->assertEquals(
      strtotime(date('Y-m-d', $future_timestamp) . ' 2:00:00'),
      $state->get('eu_oss_catalogue.last_check')
    );
    // Then the "eu_oss_catalogue_cron" job has run 2 times.
    $this->assertEquals(2, $this->getJobRunCount('eu_oss_catalogue_cron'));
  }

  /**
   * Get the number of times a specific cron job has run.
   *
   * @param string $job
   *   The name of the cron job.
   *
   * @return int
   *   The number of times the cron job has run.
   */
  protected function getJobRunCount(string $job): int {
    $search_text = 'Cron ' . $job . ' ran successfully.';
    // Query the database to count the number of times the job has run.
    $query = \Drupal::database()->select('watchdog', 'wd')
      ->condition('type', 'eu_oss_catalogue')
      ->condition('message', '%' . $search_text . '%', 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();
    return (int) $query;
  }

}
