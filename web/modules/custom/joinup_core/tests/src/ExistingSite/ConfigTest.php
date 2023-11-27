<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Core\Url;
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
   * {@inheritdoc}
   */
  protected bool $disableSpamProtection = FALSE;

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
   */
  public function testInspectConfiguration(): void {
    try {
      $this->drush('config:inspect', [], [
        'only-error' => NULL,
        'fields' => 'key',
        'format' => 'csv',
      ]);
    }
    catch (\Throwable $exception) {
      // This Drush commands doesn't output a consistent JSON, we have to hack
      // in output trying to extract the list of offended keys.
      [, $keys] = explode('Key', $this->getOutputRaw());
      $keys = implode(', ', array_filter(array_map('trim', explode("\n", $keys))));
      $this->fail("Configuration(s) with errors: $keys. Check " . Url::fromRoute('config_inspector.overview')->setAbsolute()->toString() . ' for details');
    }
  }

}
