<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_tmgmt\Kernel;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\tmgmt_content\Kernel\ContentEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that IframeFieldProcessor keeps iframe machine properties untranslated.
 *
 * @see \Drupal\Tests\tmgmt_content\Kernel\ContentEntityLinkTest
 */
#[Group('joinup_tmgmt')]
class IframeFieldProcessorTest extends ContentEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'joinup_tmgmt',
    'iframe',
    'path',
    'path_alias',
    'token',
    'pathauto',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_iframe',
      'entity_type' => 'entity_test_mul',
      'type' => 'iframe',
      'cardinality' => 1,
      'translatable' => TRUE,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_storage' => $field_storage,
      'bundle' => 'entity_test_mul',
      'label' => 'Iframe',
    ])->save();
  }

  /**
   * Only the iframe title is translatable; machine properties are not.
   */
  public function testMachinePropertiesAreNotTranslated(): void {
    $entity = EntityTestMul::create([
      'langcode' => 'en',
      'name' => 'Llama',
      'field_iframe' => [
        'title' => 'Llama title',
        'url' => 'https://example.com/embed',
        'scrolling' => 'auto',
        'width' => '100%',
        'height' => '500',
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

    // Only the title is extracted for translation.
    $this->assertTrue($data['field_iframe'][0]['title']['#translate']);
    foreach (['url', 'scrolling', 'width', 'height'] as $machine) {
      $this->assertFalse($data['field_iframe'][0][$machine]['#translate'], "$machine must not be translated");
    }

    // Accept the translation: the title is translated, the machine values are
    // left untouched.
    $job->requestTranslation();
    $items = $job->getItems();
    reset($items)->acceptTranslation();

    $translation = EntityTestMul::load($entity->id())->getTranslation('de');
    $this->assertEquals('de(de-ch): Llama title', $translation->get('field_iframe')->title);
    $this->assertEquals('auto', $translation->get('field_iframe')->scrolling);
    $this->assertEquals('https://example.com/embed', $translation->get('field_iframe')->url);
  }

}
