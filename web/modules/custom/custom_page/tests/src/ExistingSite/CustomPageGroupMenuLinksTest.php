<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_page\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\og\OgGroupAudienceHelperInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

/**
 * Tests the custom page group menu links.
 */
#[Group('custom_page')]
#[RunClassInSeparateProcess]
class CustomPageGroupMenuLinksTest extends JoinupExistingSiteTestBase {

  /**
   * Tests the correct langcode and updateLinkTitles() works.
   */
  public function testUpdateCustomPageTitles(): void {
    $this->drupalLogin($this->createUser(values: ['roles' => ['moderator']]));

    $assert = $this->assertSession();
    $collection = \Drupal::service('entity.repository')->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_COLLECTION_UUID);

    $page = $this->createNode([
      'type' => 'custom_page',
      'title' => 'EN title',
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $collection->id(),
      'field_state' => 'published',
    ]);
    $this->assertInstanceOf(CustomPageInterface::class, $page);
    $this->assertGroupMenuLinks($page);
    $this->drupalGet($collection->toUrl());
    $assert->linkExists('EN title');

    $page->addTranslation('fr', ['title' => 'FR title'] + $page->toArray())->save();
    $frenchUrl = $collection->toUrl()->setOption('language', ConfigurableLanguage::load('fr'));
    $this->drupalGet($frenchUrl);
    $assert->linkExists('FR title');

    $page->getTranslation('fr')->set('title', 'FR title updated')->save();
    $this->drupalGet($frenchUrl);
    $assert->linkExists('FR title updated');
  }

  /**
   * Asserts created custom page group menu links.
   *
   * @param \Drupal\custom_page\Entity\CustomPageInterface $customPage
   *   The custom page entity.
   */
  protected function assertGroupMenuLinks(CustomPageInterface $customPage): void {
    foreach ($customPage->getGroup()->getGroupOgMenuInstances() as $id => $ogMenuInstance) {
      $links = \Drupal::service('plugin.manager.menu.link')->loadLinksByRoute(
        'entity.node.canonical',
        ['node' => $customPage->id()],
        "ogmenu-$id",
      );
      $this->assertCount(1, $links);
    }
  }

}
