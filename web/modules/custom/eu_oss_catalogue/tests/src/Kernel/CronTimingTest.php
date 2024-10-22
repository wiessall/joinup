<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
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
    'eu_oss_catalogue_test_not_allowed_mock',
    'http_request_mock',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('dblog', ['watchdog']);
    date_default_timezone_set('UTC');
  }

  /**
   * Tests cron periodical task.
   */
  public function testCronPeriodicalTask(): void {
    $state = \Drupal::state();
    $cron = \Drupal::service('cron');
    $today = (new \DateTime('now'))->setTime(2, 0)->getTimestamp();

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
    $future_timestamp = new \DateTime('+2 days');
    $mocked_time = $this->createMock(TimeInterface::class);
    $mocked_time
      ->expects($this->any())
      ->method('getRequestTime')
      ->willReturn($future_timestamp->getTimestamp());
    // When 1 day and 1 minute passes
    // Replace the real DrupalDateTime class with the mocked one.
    $this->container->set('datetime.time', $mocked_time);

    // Mock queue to check if correct arguments were provided.
    $mock_queue = $this->createMock(QueueInterface::class);
    $mock_queue->expects($this->once())
      ->method('createQueue');
    $mock_queue->expects($this->any())
      ->method('createItem');
    $this->container->set('queue', $mock_queue);
    $queue_factory = $this->createMock(QueueFactory::class);
    $queue_factory->expects($this->any())
      ->method('get')
      ->with('eu_oss_catalogue_fetcher')
      ->willReturn($mock_queue);
    $this->container->set('queue', $queue_factory);

    // And I run cron.
    $cron->run();

    $this->assertEquals(
      $future_timestamp->setTime(2, 0)->getTimestamp(),
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
