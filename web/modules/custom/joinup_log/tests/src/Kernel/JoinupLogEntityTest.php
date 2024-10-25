<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_log\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\joinup_log\Entity\JoinupLog;
use Drupal\joinup_log\Entity\JoinupLogInterface;

/**
 * Test the Portal log entity functionality.
 */
class JoinupLogEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'joinup_group',
    'joinup_group',
    'joinup_log',
    'joinup_notification',
    'joinup_workflow',
    'message',
    'message_notify',
    'og',
    'options',
    'rdf_entity',
    'state_machine_revisions',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('joinup_log');
    $this->installConfig(['system']);
  }

  /**
   * Tests creation/loading of log entries.
   */
  public function testCreateLoad(): void {
    $log_entity = $this->generateLogEntity([
      'uid' => 1,
      'actor' => 2,
      'type' => 'system',
      'severity' => RfcLogLevel::NOTICE,
      'location' => '1.1.1.1',
      'referer' => '2.2.2.2',
      'hostname' => '127.0.0.1',
      'timestamp' => 1632269259,
    ]);

    $this->assertEquals(1, $log_entity->getOwnerId());
    $this->assertEquals(2, $log_entity->getActorId());
    $this->assertEquals('system', $log_entity->getType());
    $this->assertEquals(RfcLogLevel::NOTICE, $log_entity->getSeverity());
    $this->assertEquals('1.1.1.1', $log_entity->getLocation());
    $this->assertEquals('2.2.2.2', $log_entity->getReferer());
    $this->assertEquals('127.0.0.1', $log_entity->getHostname());
    $this->assertEquals(1632269259, $log_entity->getTimestamp());
  }

  /**
   * Tests that the log entries cannot be updated once created.
   */
  public function testUpdate(): void {
    $log_entity = $this->generateLogEntity();

    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Portal log entities are read only entities. Saving them once created is not allowed.');
    $log_entity->save();
  }

  /**
   * Tests deletion of log entries.
   */
  public function testDelete(): void {
    $log_entity = $this->generateLogEntity();
    $log_entity_id = $log_entity->id();
    $log_entity->delete();
    $this->assertNull(JoinupLog::load($log_entity_id));
  }

  /**
   * Generates a Portal log entity.
   *
   * @param array $values
   *   Overrides the defaults values.
   *
   * @return \Drupal\joinup_log\Entity\JoinupLogInterface
   *   The Portal log entity.
   */
  private function generateLogEntity(array $values = []): JoinupLogInterface {
    global $base_root;

    $user = !empty($this->adminUser) ? $this->adminUser : new AnonymousUserSession();
    $values = $values + [
      'type'        => 'custom',
      'message'     => 'Portal log test log message',
      'severity'    => RfcLogLevel::NOTICE,
      'uid'         => $user->id(),
      'location'    => $base_root . \Drupal::request()->getRequestUri(),
      'referer'     => \Drupal::request()->server->get('HTTP_REFERER'),
      'hostname'    => '127.0.0.1',
      'timestamp'   => \Drupal::time()->getRequestTime(),
    ];

    $log_entity = JoinupLog::create($values);
    $log_entity->save();
    return $log_entity;
  }

}
