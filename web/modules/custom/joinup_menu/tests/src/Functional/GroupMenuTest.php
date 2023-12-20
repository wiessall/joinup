<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_menu\Functional;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntity;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Test description.
 *
 * @group joinup_menu
 */
final class GroupMenuTest extends JoinupExistingSiteTestBase {

  use TestFileCreationTrait;

  /**
   * Tests the collection navigation menu.
   */
  public function testCollectionNavigationMenu(): void {
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'field_ar_state' => 'published',
    ]);

    $ogMenuInstances = $this->loadEntityOgMenuInstances($collection);
    $this->assertCount(2, $ogMenuInstances);

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[0]->id());
    $this->assertMenuLink($menuLinks[0], 'Overview', $collection->toUrl()->getInternalPath(), TRUE);

    // @todo Use the link template to get the URL.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8361
    $url = Url::fromRoute('view.discussions.overview', [
      'rdf_entity' => $collection->id(),
    ]);
    $this->assertMenuLink($menuLinks[1], 'Discussions', $url->getInternalPath(), TRUE);
  }

  /**
   * Tests the collection quick links menu.
   */
  public function testCollectionQuickLinksMenu(): void {
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'field_ar_state' => 'published',
    ]);

    $ogMenuInstances = $this->loadEntityOgMenuInstances($collection);
    $this->assertCount(2, $ogMenuInstances);
    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());

    // Assert that both menu links are enabled and both the links point to '' -
    // route:<none>.
    $this->assertMenuLink($menuLinks[0], 'Website link', '', FALSE);
    $contactUrl = Url::fromRoute('email_contact.form', [
      'entity_type' => $collection->getEntityTypeId(),
      'entity' => $collection->id(),
      'field_name' => 'field_ar_contact_information',
    ])->getInternalPath();
    $this->assertMenuLink($menuLinks[1], 'Contact us', $contactUrl, FALSE);

    // Set an access URL to the collection and re-check.
    $collection->set('field_ar_access_url', 'https://example.com');
    $collection->save();

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Contact us', $contactUrl, FALSE);

    $contactInformation = $this->createRdfEntity([
      'rid' => 'contact_information',
      'field_ci_email' => 'test@example.com',
      'field_ci_name' => 'Some name',
    ]);
    $contactInformation->save();

    // Set a contact information field to the collection and re-check.
    $collection->set('field_ar_contact_information', $contactInformation->id());
    $collection->save();

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Contact us', $contactUrl, FALSE);

    // Create an email confirmation and confirm it.
    \Drupal::getContainer()->get('contact_information.email_confirm')->confirmEmail($contactInformation->field_ci_email->value);
    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Contact us', $contactUrl, TRUE);
  }

  /**
   * Tests the solution navigation menu.
   */
  public function testSolutionNavigationMenu(): void {
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'field_ar_state' => 'published',
    ]);
    $solution = $this->createRdfEntity([
      'rid' => 'solution',
      'field_is_state' => 'published',
      'collection' => $collection->id(),
    ]);

    $ogMenuInstances = $this->loadEntityOgMenuInstances($solution);
    $this->assertCount(2, $ogMenuInstances);

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[0]->id());
    $this->assertMenuLink($menuLinks[0], 'Overview', $solution->toUrl()->getInternalPath(), TRUE);

    // @todo Use the link template to get the URL.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8361
    $url = Url::fromRoute('view.discussions.overview', [
      'rdf_entity' => $solution->id(),
    ]);
    $this->assertMenuLink($menuLinks[1], 'Discussions', $url->getInternalPath(), TRUE);
  }

  /**
   * Tests the solution quick links menu.
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

    $ogMenuInstances = $this->loadEntityOgMenuInstances($solution);
    $this->assertCount(2, $ogMenuInstances);
    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertCount(4, $menuLinks);

    // Assert that both menu links are enabled and both the links point to '' -
    // route:<none>.
    $this->assertMenuLink($menuLinks[0], 'Website link', '', FALSE);
    $releasesUrl = Url::fromRoute('asset_release.solution_asset_release.overview', [
      'rdf_entity' => $solution->id(),
    ])->getInternalPath();

    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl, TRUE);
    $this->assertMenuLink($menuLinks[2], 'Documentation', '', FALSE);
    $contactUrl = Url::fromRoute('email_contact.form', [
      'entity_type' => $solution->getEntityTypeId(),
      'entity' => $solution->id(),
      'field_name' => 'field_is_contact_information',
    ])->getInternalPath();
    $this->assertMenuLink($menuLinks[3], 'Contact us', $contactUrl, FALSE);

    // Set an access URL to the solution and re-check.
    $solution->set('field_is_landing_page', 'https://example.com');
    $solution->save();

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl, TRUE);
    $this->assertMenuLink($menuLinks[2], 'Documentation', '', FALSE);
    $this->assertMenuLink($menuLinks[3], 'Contact us', $contactUrl, FALSE);

    // Create a dummy text file with dummy contents.
    $fileUri = $this->generateFile('test', 10, 10, 'text');
    $file = File::create([
      'uri' => $fileUri,
    ]);
    $file->setPermanent();
    $file->save();

    // Set a documentation field to the solution and re-check.
    $solution->set('field_is_documentation', $file->id());
    $solution->save();

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl, TRUE);
    $this->assertMenuLink($menuLinks[2], 'Documentation', 'public:/' . $file->createFileUrl(), TRUE);
    $this->assertMenuLink($menuLinks[3], 'Contact us', $contactUrl, FALSE);

    $contactInformation = $this->createRdfEntity([
      'rid' => 'contact_information',
      'field_ci_email' => 'test@example.com',
      'field_ci_name' => 'Some name',
    ]);
    $contactInformation->save();

    // Set a contact information field to the solution and re-check.
    $solution->set('field_is_contact_information', $contactInformation->id());
    $solution->save();

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl, TRUE);
    $this->assertMenuLink($menuLinks[2], 'Documentation', 'public:/' . $file->createFileUrl(), TRUE);
    $this->assertMenuLink($menuLinks[3], 'Contact us', $contactUrl, FALSE);

    // Create an email confirmation and confirm it.
    \Drupal::getContainer()->get('contact_information.email_confirm')->confirmEmail($contactInformation->field_ci_email->value);
    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl, TRUE);
    $this->assertMenuLink($menuLinks[2], 'Documentation', 'public:/' . $file->createFileUrl(), TRUE);
    $this->assertMenuLink($menuLinks[3], 'Contact us', $contactUrl, TRUE);

    // Create the EIF perspective for the solution.
    $metaEntity = RdfMetaEntity::loadOrCreate('eif_perspective', $solution);
    $metaEntity->save();
    $eifPerspectiveUrl = Url::fromRoute('entity.rdf_entity.eif_perspective', [
      'rdf_entity' => $solution->id(),
    ])->getInternalPath();

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[1]->id());
    $this->assertCount(5, $menuLinks);
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Release history', $releasesUrl, TRUE);
    $this->assertMenuLink($menuLinks[2], 'Documentation', 'public:/' . $file->createFileUrl(), TRUE);
    $this->assertMenuLink($menuLinks[3], 'Contact us', $contactUrl, TRUE);
    // By default the menu item is disabled.
    // @see \_eif_add_eif_link_to_leftside_menu
    $this->assertMenuLink($menuLinks[4], 'EIF Perspective', $eifPerspectiveUrl, FALSE);
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
   * @param bool $enabled
   *   The expected enabled status.
   */
  protected function assertMenuLink(MenuLinkContentInterface $menuLink, string $title, string $url, bool $enabled): void {
    $this->assertEquals($title, $menuLink->getTitle());
    $actualUri = $menuLink->getUrlObject()->isExternal() ? $menuLink->getUrlObject()->getUri() : $menuLink->getUrlObject()->getInternalPath();
    $this->assertEquals($url, $actualUri);
    $this->assertEquals($enabled, $menuLink->isEnabled());
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
