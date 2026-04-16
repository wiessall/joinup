<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FormTrait;
use Drupal\joinup\Traits\OgTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\og\OgRoleInterface;
use Drupal\user\Entity\User;

/**
 * Behat step definitions for testing collections.
 */
class CollectionContext extends RawDrupalContext {

  use EntityTrait;
  use FormTrait;
  use OgTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * Static cache for Joinup collection.
   *
   * @see self::getJoinupCollection()
   */
  protected CollectionInterface $joinupCollection;

  /**
   * Navigates to the propose collection form.
   */
  #[When('I go to the propose collection form')]
  public function visitProposeCollectionForm(): void {
    $this->visitPath('propose/collection');
  }

  /**
   * Navigates to the Code of Conduct consent page of a collection.
   *
   * @param string $collection
   *   The title of the collection.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  #[When('I visit the :collection Code of Conduct consent page')]
  public function visitCodeOfConductConsentPage(string $collection): void {
    $entity = $this->getEntityByLabel('node', $collection, 'collection');

    $url = Url::fromRoute('collection.code_of_conduct_consent', [
      'node' => $entity->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Updates the Code of Conduct for a collection (creates a new revision).
   *
   * @param string $title
   *   The title of the collection.
   * @param string $operation
   *   The operation to perform (updated, published, unpublished).
   */
  #[Given('the Code of Conduct for :title collection is :operation')]
  public function updateCodeOfConductPage(string $title, string $operation): void {
    if (!in_array($operation, ['updated', 'published', 'unpublished'], TRUE)) {
      throw new \InvalidArgumentException();
    }

    $entity = self::getEntityByLabel('node', $title, 'collection');
    \assert($entity instanceof CollectionInterface);

    $coc = $entity->getCodeOfConduct();
    \assert($coc instanceof CustomPageInterface);
    switch ($operation) {
      case 'published':
      case 'updated':
        $coc->setNewRevision();
        $coc->setWorkflowState('published');
        $coc->save();
        break;

      case 'unpublished':
        $coc->setNewRevision();
        $coc->setWorkflowState('unpublished');
        $coc->save();
        break;
    }
  }

  /**
   * Checks that the user is on the given collection homepage.
   *
   * @param string $collection
   *   The title of the collection.
   */
  #[Then('I should be on (the homepage of )the :collection collection')]
  public function assertCollectionPath(string $collection): void {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->getEntityByLabel('node', $collection, 'collection');
    $this->assertSession()->addressEquals($this->locatePath($entity->toUrl()->toString()));
  }

  /**
   * Navigates to the collections overview page.
   *
   * @todo This is currently dependent on the Joinup profile being installed,
   *   since the view providing this overview page is exported in the profile.
   *   Remove this todo when ISAICP-5176 is fixed.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-5176
   */
  #[Given('I visit the collection overview page')]
  public function visitCollectionOverviewPage(): void {
    $this->visitPath('/collections');
  }

  /**
   * Enriches the provided field values with defaults for missing values.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   */
  #[BeforeNodeCreate]
  public function defaultCollectionValues(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'collection') {
      return;
    }

    $default_values = [
      'state' => 'published',
      'abstract' => 'This is a test abstract.',
    ];

    foreach ($default_values as $field => $default_value) {
      if (!isset($node->{$field})) {
        $node->{$field} = $default_value;
      }
    }

    $mapping = [
      'closed' => ['no' => 0, 'yes' => 1],
      'state' => [
        'draft' => 'draft',
        'proposed' => 'proposed',
        'published' => 'published',
        'archival request' => 'archival_request',
        'archived' => 'archived',
      ],
    ];

    foreach ($mapping as $field => $values) {
      if (isset($node->{$field}) && isset($values[$node->{$field}])) {
        $node->{$field} = $values[$node->{$field}];
      }
    }
  }

  /**
   * Actions to be performed after creating a collection.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope $scope
   *   The after node create scope.
   */
  public function afterCollectionCreate(AfterNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'collection') {
      return;
    }

    // If the collection is featured we need to create the meta entity that
    // stores this information after creating the collection.
    $is_featured = in_array(strtolower((string) ($node->feature ?? '')), [
      'y',
      'yes',
    ]);

    if ($is_featured) {
      $node->feature();
    }
  }

  /**
   * Subscribes the given users to the given collections.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | collection               | user          | roles                      | state   |
   * | Interoperability Friends | Verence II    | facilitator, administrator | active  |
   * | Electronic Surveillance  | Letice Earwig |                            | blocked |
   * @codingStandardsIgnoreEnd
   *
   * Only the 'collection' and 'user' columns are required. Multiple comma
   * separated roles can be passed.
   *
   * The state can be empty, or one of 'active', 'pending', or 'blocked'. If the
   * state is omitted it will default to 'active'.
   *
   * @param \Behat\Gherkin\Node\TableNode $membership_table
   *   The membership table.
   *
   * @throws \Exception
   *   Thrown when a collection is not found.
   */
  #[Given('(the following )collection user membership(s):')]
  public function givenCollectionUserMemberships(TableNode $membership_table): void {
    foreach ($membership_table->getColumnsHash() as $values) {
      $group = $this->getEntityByLabel('node', $values['collection']);
      $this->givenUserMembership($group, $values);
    }
  }

  /**
   * Alters custom page menu items for group navigation menus.
   *
   * It does not create the menu items as they are created automatically when
   * the custom pages are created.
   *
   * Table format:
   * | title              | parent              | weight | enabled |
   * | Custom page parent | Custom page child 1 |      1 | yes     |
   * | Custom page parent | Custom page child 2 |      3 | yes     |
   * | Custom page parent | Custom page child 3 |      2 | no      |
   *
   * All columns except the 'title' column are optional.
   *
   * @param \Behat\Gherkin\Node\TableNode $menu_table
   *   The menu table.
   * @param string $menu_type
   *   The OG menu instance bundle. Either 'main' or 'navigation'.
   * @param string $group_label
   *   The label of the group entity.
   * @param string $group_bundle
   *   The bundle of the group entity.
   *
   * @throws \Exception
   *    Throws an exception if the parent item is not found.
   */
  #[Given('(the following )custom page(s) :menu_type menu structure for the :group_label :group_bundle:')]
  public function givenCollectionMenuStructure(TableNode $menu_table, string $menu_type, string $group_label, string $group_bundle): void {
    $group = $this->getEntityByLabel('node', $group_label, $group_bundle);
    $menu_link_manager = \Drupal::getContainer()->get('plugin.manager.menu.link');
    foreach ($menu_table->getColumnsHash() as $values) {
      if (!empty($values['parent'])) {
        $parent_link = $this->getMenuLinkByTitle($values['parent'], $group->id(), $menu_type);
        $values['parent'] = $parent_link->getPluginId();
      }

      if (!empty($values['enabled'])) {
        $values['enabled'] = (int) ($values['enabled'] === 'yes');
      }

      $child_link = $this->getMenuLinkByTitle($values['title'], $group->id(), $menu_type);
      $menu_link_manager->updateDefinition($child_link->getPluginId(), $values);
    }
  }

  /**
   * Asserts that a user is an owner of the given collection.
   *
   * To be owner of a collection, a user should be an administrator,
   * a facilitator and a member.
   *
   * @param string $username
   *   The name of the user.
   * @param string $entity
   *   The label of the group entity.
   *
   * @throws \Exception
   *    Throws an exception when the user is not found.
   */
  protected function assertCollectionOwnership(string $username, string $entity): void {
    $user = user_load_by_name($username);
    if (!$user instanceof AccountInterface) {
      throw new \Exception("User {$username} could not be found.");
    }

    $collection = $this->getEntityByLabel('node', $entity, 'collection');
    $owner_roles = [
      OgRoleInterface::ADMINISTRATOR,
      OgRoleInterface::AUTHENTICATED,
      'facilitator',
    ];

    $this->assertOgGroupOwnership($user, $collection, $owner_roles);
  }

  /**
   * Asserts that the current user is an owner of the given collection.
   *
   * @param string $entity
   *   The label of the group entity.
   *
   * @throws \Exception
   *    Throws an exception when the user is not found.
   *
   * @see assertCollectionOwnership()
   */
  #[Given('I should own the :entity collection')]
  public function assertCollectionOwnershipCurrentUser(string $entity): void {
    if (!$current_user = $this->userManager->getCurrentUser()) {
      throw new \Exception("No current user.");
    }
    $this->assertCollectionOwnership($current_user->name, $entity);
  }

  /**
   * Checks that a user has the available state options for the collection.
   *
   * The method also checks that these options are the only options available.
   * This method will log in as each user in sequence, so take care to only use
   * it when the currently logged in user can be discarded.
   *
   * Table format:
   * | collection   | user | buttons         |
   * | Collection A | John | Save as draft   |
   * | Collection B | Jack | Update, Publish |
   *
   * @param \Behat\Gherkin\Node\TableNode $check_table
   *   The table with the triplets collection-user-buttons.
   *
   * @throws \Exception
   *    Thrown when the user does not exist.
   *
   * @todo Maybe there is a better definition available here like 'The
   * following state buttons should be available for the user on the
   * collection'.
   */
  #[Then('for the following collection, the corresponding user should have the corresponding (available )state buttons:')]
  public function verifyStateButtons(TableNode $check_table): void {
    foreach ($check_table->getColumnsHash() as $values) {
      $username = $values['user'];
      $user = $this->userManager->getUser($username);

      // Check if we are already logged in as the user to test.
      $current_username = $this->userManager->getCurrentUser()->name ?? NULL;
      if ($username !== $current_username) {
        $this->authenticationManager->logIn($user);
      }

      // Go to the edit form and check that the expected buttons are visible.
      $entity = $this->getEntityByLabel('node', $values['collection'], 'collection');
      $this->visitPath($entity->toUrl('edit-form')->toString());
      $buttons = $this->explodeDelimitedSeparatedStepArgument($values['buttons']);
      $this->assertSubmitButtonsVisible($buttons);
    }
  }

  /**
   * Checks that a user has access to the delete button on the collection form.
   *
   * Table format:
   * | collection   | user | delete link |
   * | Collection A | John | yes         |
   * | Collection B | Jack | no          |
   *
   * @param \Behat\Gherkin\Node\TableNode $check_table
   *   The table with the triplets collection-user-link visibility.
   *
   * @throws \Exception
   *    Thrown when the user does not exist.
   */
  #[Then('the visibility of the delete link should be as follows for these users in these collections:')]
  public function verifyDeleteLinkVisibility(TableNode $check_table): void {
    foreach ($check_table->getColumnsHash() as $values) {
      $user = $this->getUserByName($values['user']);
      $collection = $this->getEntityByLabel('node', $values['collection']);
      $visible = $values['delete link'] === 'yes';
      $this->assertGroupEntityOperation($visible, 'delete', $collection, $user);
    }
  }

  /**
   * Navigates to the collection leave confirmation form.
   *
   * @param string $label
   *   The label of the collection group.
   */
  #[Given('I am about to leave( the) :label( collection)')]
  public function visitCollectionLeaveConfirmationPage(string $label): void {
    $collection = $this->getEntityByLabel('node', $label, 'collection');

    $url = Url::fromRoute('joinup_group.leave_confirm_form', [
      'node' => $collection->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Asserts that a given or current user is member of collection list.
   *
   * @param string $labels
   *   A string of comma separated labels.
   * @param string|null $user_name
   *   The user name related to the assertion.
   *
   * @throws \Exception
   *   Thrown if a logged in user was not found or it was not passed, or if the
   *   assertion failed.
   */
  #[Then('I am member of :labels collection(s)')]
  #[Then('user :user_name is member of :labels collection(s)')]
  public function assertUserIsMemberOfCollection(string $labels, ?string $user_name = NULL): void {
    /** @var \Drupal\user\UserInterface $account */
    if (!$user_name) {
      $user = $this->getUserManager()->getCurrentUser();
      $account = User::load($user->uid);
      if ($account->isAnonymous()) {
        throw new \Exception("No current logged in user.");
      }
    }
    else {
      $account = $this->getUserByName($user_name);
    }

    $failures = [];
    foreach ($this->explodeDelimitedSeparatedStepArgument($labels) as $label) {
      /** @var \Drupal\collection\Entity\CollectionInterface $collection */
      $collection = $this->getEntityByLabel('node', $label, 'collection');
      if (!$collection->getMembership($account->id(), [])) {
        $failures[] = $label;
      }
    }

    if ($failures) {
      throw new \Exception("User $user_name is not member of the next collections: '" . implode("', '", $failures) . "'");
    }
  }

  /**
   * Updates the name of a collection.
   *
   * @param string $collection
   *   The name of the collection to update.
   * @param string $name
   *   The new anem for the collection.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the updated collection cannot be saved.
   */
  #[When('I change the name of the :collection collection to :name')]
  public function updateCollectionName(string $collection, string $name): void {
    $entity = $this->getEntityByLabel('node', $collection);
    $entity->set('title', $name)->save();
  }

  /**
   * Asserts that a glossary navigator is present or not on the page.
   *
   * @param string|null $expected_navigator
   *   The navigator links.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   The navigator is missing from the page.
   */
  #[Then('I should see the glossary navigator :expected_navigator')]
  #[Then('I should not see the glossary navigator')]
  public function assertGlossaryNavigator(?string $expected_navigator = NULL): void {
    $page = $this->getSession()->getPage();
    $navigator_node = $page->find('css', '.glossary-navigator');

    if (!$expected_navigator) {
      if ($navigator_node) {
        throw new ExpectationException("The glossary navigator exists on the page but it should not.", $this->getSession()->getDriver());
      }
      return;
    }

    if (!$navigator_node) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'navigator');
    }

    $actual_navigator = $navigator_node->getText();
    if ($actual_navigator !== $expected_navigator) {
      throw new ExpectationException("Expected navigator '{$expected_navigator}' but found '{$actual_navigator}'.", $this->getSession()->getDriver());
    }
  }

  /**
   * Sets a collection on content that requires one.
   *
   * Some tests deal with collection content but don't care about which
   * collection the content belongs to. It is tedious to manually define a
   * collection and link it to the content for every single test. If it is
   * omitted in the definition of the test we assign the existing Joinup
   * collection. This ensures data integrity and prevents validation errors.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   An object containing the entity properties and fields that are to be used
   *   for creating the node as properties on the object.
   */
  #[BeforeNodeCreate]
  public function provideCollection(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();

    // Skip if a collection or solution has been assigned.
    if (!empty($node->collection) || !empty($node->solution) || !empty($node->{OgGroupAudienceHelper::DEFAULT_FIELD})) {
      return;
    }

    $collection_content_bundles = [
      'custom_page',
      'glossary',
      'solution',
      ...CommunityContentHelper::BUNDLES,
    ];

    // Only deal with collection content.
    if (in_array($node->type, $collection_content_bundles, TRUE)) {
      $node->collection = $this->getJoinupCollection()->label();
    }
  }

  /**
   * Assigns a ghost SVG logo.
   *
   * @param string $title
   *   The collection title.
   */
  #[Given('the :title collection has a logo with a missing physical file')]
  public function setCollectionGhostLogo(string $title): void {
    $filename = 'ghost-logo-' . uniqid() . '.svg';
    $file = \Drupal::entityTypeManager()->getStorage('file')->create([
      'uri' => 'public://' . $filename,
      'filename' => $filename,
      'filemime' => 'image/svg+xml',
      'status' => 1,
    ]);
    $file->save();
    $node = static::getEntityByLabel('node', $title, 'collection');
    $node->set('logo', ['target_id' => $file->id(), 'alt' => 'Ghost logo']);
    $node->save();
  }

  /**
   * Returns and caches the Joinup collection.
   *
   * @return \Drupal\collection\Entity\CollectionInterface
   *   The Joinup collection.
   */
  protected function getJoinupCollection(): CollectionInterface {
    if (!isset($this->joinupCollection)) {
      $this->joinupCollection = \Drupal::getContainer()->get('rdf_sync.mapper')
        ->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);
    }
    return $this->joinupCollection;
  }

}
