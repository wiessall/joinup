<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the Joinup configuration.
 *
 * @group joinup_core
 */
class ConfigTest extends JoinupExistingSiteTestBase {

  use DrushTestTrait;

  /**
   * Tests that the active and sync stores are the same.
   */
  public function testConfig(): void {
    $this->drush('config:status', [], ['format' => 'json']);
    $diff = array_keys((array) $this->getOutputFromJSON());

    // Check that there are no differences between database and config sync.
    $this->assertEmpty($diff, 'Differences between active and sync stores for: ' . implode(', ', $diff));
  }

  /**
   * Tests that configurations passes config_inspector checks.
   *
   * @usage ddev drush config:inspect --only-error --detail --list-constraints
   *
   * To resolve issues, inspect $raw_violations using xdebug breakpoints in:
   * @see \Drupal\config_inspector\Commands\InspectorCommands::inspect
   */
  public function testInspectConfiguration(): void {
    $this->expectNotToPerformAssertions();
    $this->drush('config:inspect', [], [
      'only-error' => NULL,
      'detail' => NULL,
      'list-constraints' => NULL,
    ]);
  }

}
