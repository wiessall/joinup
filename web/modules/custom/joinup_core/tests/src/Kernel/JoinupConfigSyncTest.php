<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\config_test\TestInstallStorage;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests Joinup sync configuration.
 *
 * @group joinup_core
 */
class JoinupConfigSyncTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // This module alters the schema of facet.widget.default_config.
    // @see search_api_arbitrary_facet_config_schema_info_alter()
    'search_api_arbitrary_facet',
    'system',
    // This module alters the schema of field.formatter.settings.image.
    // @see svg_image_config_schema_info_alter()
    'svg_image',
  ];

  /**
   * {@inheritdoc}
   */
  public static $configSchemaCheckerExclusions = [
    // Slick module has incorrect filter plugin settings schema.
    // @todo Fix in ISAICP-8296.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8296.
    'filter.format.basic_html',
    'filter.format.content_editor',
    'filter.format.eira_format',
    'filter.format.essential_html',
    'filter.format.full_html',
    'filter.format.glossary_html',
    'filter.format.licence',
    'filter.format.minimal',
    'filter.format.plain_text',
    'filter.format.restricted_html',
    'filter.format.simple_html',
    'filter.format.simple_text',
    'filter.format.stripped_html',
    'filter.format.text_html',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Use a testing schema storage service that records all schemas from all
    // extensions, regardless if they are installed or not.
    $container->register('joinup_config_test.schema_storage')
      ->setClass(TestInstallStorage::class)
      ->addArgument(InstallStorage::CONFIG_SCHEMA_DIRECTORY);
    $container->getDefinition('config.typed')
      ->replaceArgument(1, new Reference('joinup_config_test.schema_storage'));
  }

  /**
   * Tests that config/sync configurations adhere to their schema.
   */
  public function testDefaultConfig(): void {
    $typed_config = $this->container->get('config.typed');
    $config_sync_dir = realpath("{$this->root}/../config/sync");
    $config_sync_storage = new FileStorage($config_sync_dir);
    foreach ($config_sync_storage->listAll() as $config_name) {
      if (in_array($config_name, static::$configSchemaCheckerExclusions, TRUE)) {
        continue;
      }
      $config_data = $config_sync_storage->read($config_name);
      $this->assertConfigSchema($typed_config, $config_name, $config_data);
    }
  }

}
