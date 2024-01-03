<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_menu\Functional;

use Drupal\Core\Url;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\file\Entity\File;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\Entity\Node;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntity;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Tests the menus of the groups.
 *
 * @group joinup_menu
 */
final class GroupMenuTest extends JoinupExistingSiteTestBase {

  use TestFileCreationTrait;

  /**
   * Tests the collection quick-links menu.
   */
  public function testCollectionQuickLinksMenu(): void {
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'field_ar_state' => 'published',
    ]);
    $urlParameters = ['rdf_entity' => UriEncoder::encodeUrl($collection->id())];

    $ogMenuInstances = $this->loadEntityOgMenuInstances($collection);
    $this->assertCount(2, $ogMenuInstances);
    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());

    // Assert the website link for the collection.
    $websiteUrl = Url::fromRoute('joinup_menu.website_link', $urlParameters)->getInternalPath();
    $this->assertMenuLink($menuLinks[0], 'Website link', $websiteUrl);

    // Assert the website link for the collection.
    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringNotContainsString($websiteUrl, $renderedMenu);

    // Set an access URL to the collection and re-check.
    $collection->set('field_ar_access_url', 'https://example.com');
    $collection->save();

    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringContainsString($websiteUrl, $renderedMenu);

    $authenticatedUser = $this->createUser();
    $this->drupalLogin($authenticatedUser);

    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringContainsString($websiteUrl, $renderedMenu);
  }

  /**
   * Tests the solution quick-links menu.
   */
  public function testSolutionQuickLinksMenu(): void {
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'field_ar_state' => 'published',
    ]);
    $solution = $this->createRdfEntity([
      'rid' => 'solution',
      'field_is_state' => 'published',
      'collection' => $collection->id(),
    ]);
    $urlParameters = ['rdf_entity' => UriEncoder::encodeUrl($solution->id())];

    $ogMenuInstances = $this->loadEntityOgMenuInstances($solution);
    $this->assertCount(2, $ogMenuInstances);
    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertCount(3, $menuLinks);

    $websiteUrl = Url::fromRoute('joinup_menu.website_link', $urlParameters)->getInternalPath();
    $this->assertMenuLink($menuLinks[0], 'Website link', $websiteUrl);
    $releasesUrl = Url::fromRoute('asset_release.solution_asset_release.overview', [
      'rdf_entity' => $solution->id(),
    ])->getInternalPath();
    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl);
    $documentationUrl = Url::fromRoute('joinup_menu.documentation_file', $urlParameters)->getInternalPath();
    $this->assertMenuLink($menuLinks[2], 'Documentation', $documentationUrl);

    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringNotContainsString($websiteUrl, $renderedMenu);
    $this->assertStringContainsString($releasesUrl, $renderedMenu);
    $this->assertStringNotContainsString($documentationUrl, $renderedMenu);

    // Set an access URL to the solution and re-check.
    $solution->set('field_is_landing_page', 'https://example.com');
    $solution->save();

    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringContainsString($websiteUrl, $renderedMenu);
    $this->assertStringContainsString($releasesUrl, $renderedMenu);
    $this->assertStringNotContainsString($documentationUrl, $renderedMenu);

    // Create a dummy text file with dummy contents.
    $fileUri = $this->generateFile('test', 10, 10, 'text');
    $file = File::create([
      'uri' => $fileUri,
    ]);
    $file->setPermanent();
    $file->save();
    $documentationUrl = Url::fromRoute('joinup_menu.documentation_file', $urlParameters)->getInternalPath();

    // Set a documentation field to the solution and re-check.
    $solution->set('field_is_documentation', $file->id());
    $solution->save();

    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringContainsString($websiteUrl, $renderedMenu);
    $this->assertStringContainsString($releasesUrl, $renderedMenu);
    $this->assertStringContainsString($documentationUrl, $renderedMenu);

    // Create the EIF perspective for the solution.
    $metaEntity = RdfMetaEntity::loadOrCreate('eif_perspective', $solution);
    $metaEntity->save();
    $eifPerspectiveUrl = Url::fromRoute('entity.rdf_entity.eif_perspective', [
      'rdf_entity' => $solution->id(),
    ])->getInternalPath();

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertCount(4, $menuLinks);
    $this->assertMenuLink($menuLinks[0], 'Website link', $websiteUrl);
    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl);
    $this->assertMenuLink($menuLinks[2], 'Documentation', $documentationUrl);
    // By default the menu item is disabled.
    // @see \_eif_add_eif_link_to_leftside_menu
    $this->assertMenuLink($menuLinks[3], 'EIF Perspective', $eifPerspectiveUrl);

    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringContainsString($websiteUrl, $renderedMenu);
    $this->assertStringContainsString($releasesUrl, $renderedMenu);
    $this->assertStringContainsString($documentationUrl, $renderedMenu);
    $this->assertStringNotContainsString($eifPerspectiveUrl, $renderedMenu);

    $authenticatedUser = $this->createUser();
    $this->drupalLogin($authenticatedUser);

    $renderedMenu = $this->getRenderedMenu('ogmenu-' . $ogMenuInstances[1]->id());
    $this->assertStringContainsString($websiteUrl, $renderedMenu);

    // Create a custom page in the collection.
    $custom_page = Node::create([
      'type' => 'custom_page',
      'title' => $this->randomString(),
      'og_audience' => $solution->id(),
    ]);
    $custom_page->save();

    // Check that a corresponding menu link has been created.
    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[4], $custom_page->label(), $custom_page->toUrl()->getInternalPath());
  }

  /**
   * Renders a menu.
   *
   * @param string $menuName
   *   The menu name.
   *
   * @return string
   *   The rendered menu.
   */
  protected function getRenderedMenu(string $menuName): string {
    $menuTree = \Drupal::menuTree();
    $parameters = $menuTree->getCurrentRouteMenuTreeParameters($menuName);
    $tree = $menuTree->load($menuName, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menuTree->transform($tree, $manipulators);
    $build = $menuTree->build($tree);
    return (string) \Drupal::service('renderer')->renderRoot($build);
  }

  /**
   * Asserts a menu link.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $menuLink
   *   The menu link.
   * @param string $title
   *   The expected title.
   * @param string $url
   *   The expected URL.
   */
  protected function assertMenuLink(MenuLinkContentInterface $menuLink, string $title, string $url): void {
    $this->assertEquals($title, $menuLink->getTitle());
    $actualUri = $menuLink->getUrlObject()->isExternal() ? $menuLink->getUrlObject()->getUri() : $menuLink->getUrlObject()->getInternalPath();
    $this->assertEquals($url, $actualUri);
  }

  /**
   * Loads a list of OG menu instances for a group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return \Drupal\og_menu\OgMenuInstanceInterface[]
   *   The list of OG menu instances.
   */
  protected function loadEntityOgMenuInstances(GroupInterface $group): array {
    $ogMenuInstanceStorage = $this->container->get('entity_type.manager')->getStorage('ogmenu_instance');
    $ogMenuInstanceIds = $ogMenuInstanceStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition(OgGroupAudienceHelper::DEFAULT_FIELD, $group->id())
      ->execute();

    return array_values($ogMenuInstanceStorage->loadMultiple($ogMenuInstanceIds));
  }

  /**
   * Returns the menu links of the given OG menu instance.
   *
   * @param string $ogMenuInstanceId
   *   The OG menu instance ID without the 'ogmenu-' prefix.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface[]
   *   An array of menu links.
   */
  protected function loadMenuLinks(string $ogMenuInstanceId): array {
    $menuLinkStorage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    $menuLinkIds = $menuLinkStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'ogmenu-' . $ogMenuInstanceId)
      ->execute();

    return array_values($menuLinkStorage->loadMultiple($menuLinkIds));
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    parent::tearDown();

    // Delete all email confirmations.
    $emailConfirmationStorage = \Drupal::entityTypeManager()->getStorage('email_confirmer_confirmation');
    $emailConfirmationIds = $emailConfirmationStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('email', 'test@example.com')
      ->condition('realm', ContactInformationEmailConfirmInterface::REALM)
      ->execute();

    $emailConfirmationStorage->delete($emailConfirmationStorage->loadMultiple($emailConfirmationIds));
  }

}
