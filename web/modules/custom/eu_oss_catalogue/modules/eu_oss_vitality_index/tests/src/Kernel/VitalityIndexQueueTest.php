<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests the cron timing logic.
 *
 * @group eu_oss_catalogue
 * @group eu_oss_vitality_index
 */
class VitalityIndexQueueTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'system',
    'eu_oss_catalogue',
    'eu_oss_catalogue_test_not_allowed_mock',
    'http_request_mock',
    'eu_oss_vitality_index',
  ];

  /**
   * Tests cron periodical task.
   */
  public function testCronPeriodicalTask(): void {
    $state = \Drupal::state();
    $cron = \Drupal::service('cron');
    $requestTime = \Drupal::time()->getRequestTime();
    $vitalityIndexQueue = \Drupal::queue('eu_oss_vitality_index');

    $this->assertEquals(0, $vitalityIndexQueue->numberOfItems());

    // Prevent pulling OSS solutions.
    $state->set('eu_oss_catalogue.last_check', strtotime(date('Y-m-d', $requestTime) . ' 2:00:00'));

    $node = $this->createNode([
      'type' => 'oss_solution',
    ]);

    $this->assertEquals(1, $vitalityIndexQueue->numberOfItems(), '1 item should be created by hook_insert');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system']);
  }

}
