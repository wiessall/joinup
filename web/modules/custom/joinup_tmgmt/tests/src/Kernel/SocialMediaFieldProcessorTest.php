<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_tmgmt\Kernel;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\tmgmt_content\Kernel\ContentEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that SocialMediaFieldProcessor keeps social-media values untranslated.
 *
 * @see \Drupal\Tests\tmgmt_content\Kernel\ContentEntityLinkTest
 */
#[Group('joinup_tmgmt')]
class SocialMediaFieldProcessorTest extends ContentEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'joinup_tmgmt',
    'social_media_links_field',
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
      'field_name' => 'field_social',
      'entity_type' => 'entity_test_mul',
      'type' => 'social_media_links_field',
      'cardinality' => 1,
      'translatable' => TRUE,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_storage' => $field_storage,
      'bundle' => 'entity_test_mul',
      'label' => 'Social media',
    ])->save();
  }

  /**
   * Neither the platform key nor the account handle is translatable.
   */
  public function testNothingIsTranslated(): void {
    $entity = EntityTestMul::create([
      'langcode' => 'en',
      'name' => 'Llama',
      'field_social' => [
        'platform' => 'linkedin',
        'value' => 'company/GovTech4All',
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

    $this->assertFalse($data['field_social'][0]['platform']['#translate']);
    $this->assertFalse($data['field_social'][0]['value']['#translate']);

    // Accept the translation: the machine values are left untouched.
    $job->requestTranslation();
    $items = $job->getItems();
    reset($items)->acceptTranslation();

    $translation = EntityTestMul::load($entity->id())->getTranslation('de');
    $this->assertEquals('linkedin', $translation->get('field_social')->platform);
    $this->assertEquals('company/GovTech4All', $translation->get('field_social')->value);
  }

}
