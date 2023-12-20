<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_menu\Functional;

use Drupal\Core\Url;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\og\OgGroupAudienceHelper;

/**
 * Test description.
 *
 * @group joinup_menu
 */
final class GroupMenuTest extends JoinupExistingSiteTestBase {

  /**
   * Tests the collection navigation menu.
   */
  public function testCollectionNavigationMenu(): void {
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'field_ar_state' => 'published',
    ]);

    $ogMenuInstances = $this->loadEntityOgMenuInstances($collection);
    $ogMenuInstances = array_values($ogMenuInstances);
    $this->assertCount(2, $ogMenuInstances);

    $menuLinks = $this->loadMenuLinks($ogMenuInstances[0]->id());
    $this->assertMenuLinks(['Overview', 'Discussions'], $menuLinks);
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
    $ogMenuInstances = array_values($ogMenuInstances);
    $this->assertCount(2, $ogMenuInstances);
    $menuLinks = array_values($this->loadMenuLinks($ogMenuInstances[1]->id()));

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

    $menuLinks = array_values($this->loadMenuLinks($ogMenuInstances[1]->id()));
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

    $menuLinks = array_values($this->loadMenuLinks($ogMenuInstances[1]->id()));
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Contact us', $contactUrl, FALSE);

    // Create an email confirmation and confirm it.
    \Drupal::getContainer()->get('contact_information.email_confirm')->confirmEmail($contactInformation->field_ci_email->value);
    $menuLinks = array_values($this->loadMenuLinks($ogMenuInstances[1]->id()));
    $this->assertMenuLink($menuLinks[0], 'Website link', 'https://example.com', TRUE);
    $this->assertMenuLink($menuLinks[1], 'Contact us', $contactUrl, TRUE);
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

    return $ogMenuInstanceStorage->loadMultiple($ogMenuInstanceIds);
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

    return $menuLinkStorage->loadMultiple($menuLinkIds);
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
