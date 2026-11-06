<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks the rdf_sync mappings in config/sync.
 */
#[Group('sti_rdf')]
final class RdfSyncConfigTest extends UnitTestCase {

  /**
   * Every synced bundle mints environment-independent URIs.
   *
   * With uri_plugin: null, rdf_sync falls back to its default generator,
   * which builds URIs from $base_url, so each environment mints different
   * URIs for the same entity. Nothing reports this at runtime.
   */
  public function testUriGeneratorSet(): void {
    $paths = glob(dirname($this->root) . '/config/sync/*.yml');
    $checked = 0;
    $violations = [];

    foreach ($paths as $path) {
      $mapping = Yaml::parseFile($path)['third_party_settings']['rdf_sync'] ?? NULL;
      if (!isset($mapping['type'])) {
        continue;
      }
      $checked++;
      // A stored URI field takes precedence over the plugin.
      if (!empty($mapping['uri_field_name'])) {
        continue;
      }
      if (empty($mapping['uri_plugin'])) {
        $violations[] = basename($path, '.yml');
      }
    }

    $this->assertGreaterThan(0, $checked, 'No rdf_sync mappings found. Is config/sync where this test expects it?');
    $this->assertSame([], $violations, 'Synced bundles without a URI generator.');
  }

}
