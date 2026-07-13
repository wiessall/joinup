<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_tmgmt\Kernel;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\tmgmt_content\Kernel\ContentEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that BlockFieldProcessor skips the plugin ID but translates the label.
 *
 * @see \Drupal\Tests\tmgmt_content\Kernel\ContentEntityLinkTest
 */
#[Group('joinup_tmgmt')]
class BlockFieldProcessorTest extends ContentEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'joinup_tmgmt',
    'block',
    'block_field',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_block',
      'entity_type' => 'entity_test_mul',
      'type' => 'block_field',
      'cardinality' => 1,
      'translatable' => TRUE,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_storage' => $field_storage,
      'bundle' => 'entity_test_mul',
      'label' => 'Block',
    ])->save();
  }

  /**
   * The plugin ID stays; the block label is translated.
   */
  public function testPluginIdNotTranslatedLabelTranslated(): void {
    $entity = EntityTestMul::create([
      'langcode' => 'en',
      'name' => 'Llama',
      'field_block' => [
        'plugin_id' => 'system_powered_by_block',
        'settings' => ['label' => 'Powered by', 'label_display' => 'visible'],
      ],
    ]);
    $entity->save();

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $item = tmgmt_job_item_create('content', 'entity_test_mul', $entity->id(), ['tjid' => $job->id()]);
    $item->save();

    $data = $this->container->get('plugin.manager.tmgmt.source')
      ->createInstance('content')
      ->getData($item);

    $this->assertFalse($data['field_block'][0]['plugin_id']['#translate']);
    $this->assertTrue($data['field_block'][0]['settings']['label']['#translate']);

    // Accept the translation: the label is translated, the plugin ID intact.
    $job->requestTranslation();
    $items = $job->getItems();
    reset($items)->acceptTranslation();

    $translation = EntityTestMul::load($entity->id())->getTranslation('de');
    $this->assertEquals('system_powered_by_block', $translation->get('field_block')->plugin_id);
    $settings = $translation->get('field_block')->settings;
    $this->assertEquals('de(de-ch): Powered by', $settings['label']);
  }

}
