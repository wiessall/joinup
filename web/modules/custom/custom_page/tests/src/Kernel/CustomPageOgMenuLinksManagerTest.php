<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_page\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\custom_page\CustomPageOgMenuLinksManager;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\joinup_group\GroupMenuManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\Entity\OgMenu;
use Drupal\og_menu\Entity\OgMenuInstance;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the custom page OG menu link updater service.
 */
#[Group('custom_page')]
class CustomPageOgMenuLinksManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'custom_page',
    'field',
    'joinup_group',
    'joinup_notification',
    'joinup_workflow',
    'language',
    'link',
    'menu_link_content',
    'menu_ui',
    'message',
    'message_notify',
    'node',
    'og',
    'og_menu',
    'og_ui',
    'options',
    'search_api',
    'state_machine',
    'state_machine_revisions',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'language',
      'og',
      'og_menu',
    ]);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('ogmenu');
    $this->installEntitySchema('ogmenu_instance');
    $this->installEntitySchema('menu_link_content');
    $this->installSchema('node', ['node_access']);

    FieldStorageConfig::create([
      'type' => 'og_standard_reference',
      'entity_type' => 'ogmenu_instance',
      'field_name' => OgGroupAudienceHelperInterface::DEFAULT_FIELD,
      'settings' => ['target_type' => 'node'],
    ])->save();
    // Create the 'navigation' OG menu.
    OgMenu::create([
      'id' => 'navigation',
      'label' => 'Navigation',
    ])->save();

    // We don't install the original 'collection' and 'custom_page' node bundle
    // definitions and fields because we want to avoid installing the whole list
    // of dependencies. Thus, we create only simplified versions of both bundles
    // for the purpose of this test.
    $mocked_collection_type = NodeType::create([
      'type' => 'collection',
      'title' => 'Mocked collection',
    ]);
    // @see og_ui_entity_type_save().
    $mocked_collection_type->og_is_group = TRUE;
    $mocked_collection_type->og_group_content_bundle = FALSE;
    $mocked_collection_type->save();

    $mocked_custom_page_type = NodeType::create([
      'type' => 'custom_page',
      'name' => 'Mocked custom page',
    ]);
    // @see og_ui_entity_type_save().
    $mocked_custom_page_type->og_group_content_bundle = TRUE;
    $mocked_custom_page_type->og_is_group = FALSE;
    $mocked_custom_page_type->og_target_type = 'node';
    $mocked_custom_page_type->og_target_bundles = ['collection'];
    $mocked_custom_page_type->save();

    // The state field plays a big part of the project, so we cannot go around
    // that much without it. We create it here manually.
    FieldStorageConfig::create([
      'type' => 'state',
      'entity_type' => 'node',
      'field_name' => 'field_state',
      'settings' => [
        'workflow' => 'custom_page_workflow',
      ],
    ])->save();

    $field = [
      'field_name' => 'field_state',
      'entity_type' => 'node',
      'bundle' => 'custom_page',
      'label' => 'State',
      'required' => TRUE,
      'settings' => [
        'workflow' => 'custom_page_workflow',
      ],
    ];
    $instance = \Drupal::service('entity_type.manager')
      ->getStorage('field_config')
      ->create($field);
    $instance->save();
  }

  /**
   * Tests the correct langcode and updateLinkTitles() works.
   */
  public function testUpdateLinkTitles(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $collection = Node::create(['type' => 'collection', 'title' => $this->randomString()]);
    $collection->save();

    /** @var \Drupal\og_menu\Entity\OgMenuInstance $ogMenuInstance */
    $ogMenuInstance = OgMenuInstance::create(['type' => 'navigation']);
    $ogMenuInstance->save();

    $manager = $this->createManagerWithMockedGroupMenu($ogMenuInstance);

    $enPage = Node::create([
      'type' => 'custom_page',
      'title' => 'EN title',
      'langcode' => 'en',
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $collection->id(),
    ]);
    $enPage->save();
    $manager->addLink($enPage, 'navigation');

    $links = $this->assertMenuLinkHelper($ogMenuInstance->id(), $enPage->id());
    $this->assertCount(1, $links);
    $this->assertSame('en', reset($links)->get('langcode')->value);

    $frPage = $enPage->addTranslation('fr', [
      'title' => 'FR title',
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $collection->id(),
    ]);

    $manager->updateLinkTitles($frPage);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $storage->resetCache();
    $links = $this->assertMenuLinkHelper($ogMenuInstance->id(), $enPage->id());
    $link = reset($links);
    $this->assertTrue($link->hasTranslation('fr'));
    $this->assertSame('FR title', $link->getTranslation('fr')->get('title')->value);

    $frPage->setTitle('FR title updated');
    $manager->updateLinkTitles($frPage);

    $storage->resetCache();
    $links = $this->assertMenuLinkHelper($ogMenuInstance->id(), $enPage->id());
    $link = reset($links);
    $this->assertSame('FR title updated', $link->getTranslation('fr')->get('title')->value);
  }

  /**
   * Returns a CustomPageOgMenuLinksManager with a mocked group menu manager.
   *
   * The mock always returns the given OG menu instance, bypassing the
   * GroupInterface check present in the real GroupMenuManager.
   *
   * @param \Drupal\og_menu\Entity\OgMenuInstance $ogMenuInstance
   *   The OG menu instance to return from the mock.
   *
   * @return \Drupal\custom_page\CustomPageOgMenuLinksManager
   *   The manager under test.
   */
  protected function createManagerWithMockedGroupMenu(OgMenuInstance $ogMenuInstance): CustomPageOgMenuLinksManager {
    $groupMenuManager = $this->createMock(GroupMenuManagerInterface::class);
    $groupMenuManager->method('getMenuByGroupId')->willReturn($ogMenuInstance);
    return new CustomPageOgMenuLinksManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('plugin.manager.menu.link'),
      $groupMenuManager,
      $this->container->get('search_api.entity_datasource.tracking_manager'),
    );
  }

  /**
   * Tests the custom page OG menu link updater service.
   */
  public function testCustomPageOgMenuLinkUpdater(): void {
    // Create 2 collections.
    $collection_ids = $ogmenu_instance_ids = [];
    foreach ([1, 2] as $index) {
      $collection_ids[$index] = Node::create([
        'type' => 'collection',
        'uri' => "http://example.com/$index",
        'title' => $this->randomString(),
      ]);
      $collection_ids[$index]->save();
      // Create also the paired OG menu link instance. Normally this is
      // automatically accomplished by the 'joinup_core' module, but we don't
      // want to enable that module due to its complex dependencies.
      // @see joinup_core_node_insert()
      $ogmenu_instance = OgMenuInstance::create([
        'type' => 'navigation',
        OgGroupAudienceHelperInterface::DEFAULT_FIELD => $collection_ids[$index]->id(),
      ]);
      $ogmenu_instance->save();
      $ogmenu_instance_ids[$index] = $ogmenu_instance;
    }
  }

  /**
   * Asserts that a menu link exists.
   *
   * @param int $og_menu_instance_id
   *   The OG menu instance ID.
   * @param int $nid
   *   The targeted custom page node ID.
   */
  protected function assertMenuLink($og_menu_instance_id, $nid): void {
    $this->assertNotEmpty($this->assertMenuLinkHelper($og_menu_instance_id, $nid));
  }

  /**
   * Asserts that a menu link doesn't exist.
   *
   * @param int $og_menu_instance_id
   *   The OG menu instance ID.
   * @param int $nid
   *   The targeted custom page node ID.
   */
  protected function assertNotMenuLink($og_menu_instance_id, $nid): void {
    $this->assertEmpty($this->assertMenuLinkHelper($og_menu_instance_id, $nid));
  }

  /**
   * Provides a helper for menu links assertions.
   *
   * @param int $og_menu_instance_id
   *   The OG menu instance ID.
   * @param int $nid
   *   The targeted custom page node ID.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface[]
   *   A list of menu link content entities.
   */
  protected function assertMenuLinkHelper($og_menu_instance_id, $nid): array {
    $properties = [
      'bundle' => 'menu_link_content',
      'menu_name' => "ogmenu-{$og_menu_instance_id}",
      'link__uri' => "entity:node/$nid",
    ];
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    return $storage->loadByProperties($properties);
  }

}
