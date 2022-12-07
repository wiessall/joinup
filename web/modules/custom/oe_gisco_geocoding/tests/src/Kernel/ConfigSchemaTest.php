<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_gisco_geocoding\Kernel;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the config schema of the GISCO Geocoding module.
 *
 * @group oe_gisco_geocoding
 */
class ConfigSchemaTest extends KernelTestBase {

  use SchemaCheckTrait;

  /**
   * The typed config manager.
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'geocoder',
    'oe_gisco_geocoding',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['geocoder']);
    $this->typedConfigManager = \Drupal::service('config.typed');
  }

  /**
   * Tests whether the config schema is correct.
   */
  public function testConfigSchema(): void {
    $config = $this->config('geocoder.settings');
    $result = $this->checkConfigSchema($this->typedConfigManager, 'geocoder.settings', $config->get());
    $this->assertTrue($result);
  }

}
