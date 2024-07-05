<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_log\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\joinup_log\Entity\JoinupLog;

/**
 * Test the Joinup log entity functionality.
 */
class JoinupLogEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['joinup_log', 'rdf_entity', 'system', 'user'];

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
    $log = JoinupLog::create([
      'uid' => 1,
      'type' => 'system',
      'message' => 'Test @a',
      'variables' => ['@a' => 'b'],
      'severity' => RfcLogLevel::NOTICE,
      'location' => '',
      'referer' => '',
      'hostname' => '127.0.0.1',
      'timestamp' => 1632269259,
    ]);
    $log->save();
    $log_loaded = JoinupLog::load(1);

    foreach ([$log, $log_loaded] as $log) {
      $this->assertEquals(1, $log->getUid());
      $this->assertEquals('system', $log->getType());
      $this->assertEquals(RfcLogLevel::NOTICE, $log->getSeverity());
      $this->assertEquals('', $log->getLocation());
      $this->assertEquals('', $log->getReferer());
      $this->assertEquals('127.0.0.1', $log->getHostname());
      $this->assertEquals(1632269259, $log->getTimestamp());
    }
  }

  /**
   * Tests deletion of log entries.
   */
  public function testDelete(): void {
    $this->generateLogEntries(1);
    $log = JoinupLog::load(1);
    $log->delete();
    $this->assertNull(JoinupLog::load(1));
  }

  /**
   * Tests that the log entries cannot be updated once created.
   */
  public function testUpdate(): void {
    $this->generateLogEntries(1);
    $log = JoinupLog::load(1);

    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Joinup log entities are read only entities. Saving them once created is not allowed.');
    $log->save();
  }

  /**
   * Tests field definitions.
   */
  public function testFieldDefinition(): void {
    $fields = [
      'id' => 'integer',
      'uid' => 'entity_reference',
      'entity_logged' => 'entity_reference',
      'type' => 'string',
      'message' => 'string',
      'variables' => 'map',
      'severity' => 'integer',
      'location' => 'string_long',
      'referer' => 'string',
      'hostname' => 'string',
      'timestamp' => 'created',
    ];

    $log = JoinupLog::create();
    foreach ($fields as $field_name => $field_type) {
      $this->assertEquals($field_name, $log->getFieldDefinition($field_name)->getName());
      $this->assertEquals($field_type, $log->getFieldDefinition($field_name)->getType());
    }

    $this->assertEquals(array_keys($log->getFields()), array_keys($fields));
    $this->assertEquals([], $log->getTranslatableFields());
  }

  /**
   * Tests format message method.
   */
  public function testFormatMessage(): void {
    $log = JoinupLog::create();
    $this->assertEquals($log->getFormattedMessage(), '');

    $log = JoinupLog::create([
      'message' => '<script>Test</script>',
      'variables' => [],
    ]);
    $this->assertEquals($log->getFormattedMessage(), 'Test');

    $log = JoinupLog::create([
      'message' => 'Testing @module module',
      'variables' => ['@module' => 'joinup_log'],
    ]);
    $this->assertEquals($log->getFormattedMessage(), 'Testing joinup_log module');
  }

  /**
   * Tests validate methods.
   */
  public function testValidate(): void {
    $log = JoinupLog::create();
    $log->validate();
    $this->assertFalse($log->isValidationRequired());
    $log->setValidationRequired(TRUE);
    $this->assertTrue($log->isValidationRequired());
  }

  /**
   * Tests toArray method.
   */
  public function testToArray(): void {
    $this->generateLogEntries(1, [
      'timestamp' => 1632269259,
      'variables' => ['@foo' => 'bar'],
    ]);
    $log = JoinupLog::load(1);
    $this->assertEquals([
      'id' => [
        [
          'value' => 1,
        ],
      ],
      'uid' => [
        [
          'target_id' => 0,
        ],
      ],
      'type' => [
        [
          'value' => 'custom',
        ],
      ],
      'message' => [
        [
          'value' => 'Joinup log test log message Entry #0',
        ],
      ],
      'variables' => [
        ['@foo' => 'bar'],
      ],
      'severity' => [
        [
          'value' => RfcLogLevel::NOTICE,
        ],
      ],
      'location' => [
        [
          'value' => 'http://localhost/',
        ],
      ],
      'referer' => [],
      'hostname' => [
        [
          'value' => '127.0.0.1',
        ],
      ],
      'timestamp' => [
        [
          'value' => 1632269259,
        ],
      ],
      'entity_logged' => [],
    ], $log->toArray());
  }

  /**
   * Generates a number of Joinup log entities.
   *
   * @param int $count
   *   Number of watchdog entries to generate.
   * @param array $values
   *   Overrides the defaults values.
   */
  private function generateLogEntries(int $count, array $values = []): void {
    global $base_root;

    $user = !empty($this->adminUser) ? $this->adminUser : new AnonymousUserSession();
    $log = $values + [
      'type'        => 'custom',
      'message'     => 'Joinup log test log message',
      'variables'   => [],
      'severity'    => RfcLogLevel::NOTICE,
      'uid'         => $user->id(),
      'location'    => $base_root . \Drupal::request()->getRequestUri(),
      'referer'     => \Drupal::request()->server->get('HTTP_REFERER'),
      'hostname'    => '127.0.0.1',
      'timestamp'   => \Drupal::time()->getRequestTime(),
    ];

    $message = $log['message'] . ' Entry #';
    for ($i = 0; $i < $count; $i++) {
      $log['message'] = $message . $i;
      $entity = JoinupLog::create($log);
      $entity->save();
    }
  }

}
