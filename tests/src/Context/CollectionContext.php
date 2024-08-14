<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\FormTrait;
use Drupal\joinup\Traits\OgTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TopicTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup\Traits\WorkflowTrait;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_group\ContentCreationOptions;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\og\OgRoleInterface;
use Drupal\og_menu\Tests\Traits\OgMenuTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Behat step definitions for testing collections.
 */
class CollectionContext extends RawDrupalContext {

  use EntityReferenceTrait;
  use EntityTrait;
  use FileTrait;
  use FormTrait;
  use OgMenuTrait;
  use OgTrait;
  use RdfEntityTrait;
  use SearchTrait;
  use TopicTrait;
  use UserTrait;
  use UtilityTrait;
  use WorkflowTrait;

  /**
   * Mapping of human readable field labels to machine names.
   */
  protected const FIELD_ALIASES = [
    'author' => 'uid',
    'uri' => 'uri',
    'title' => 'title',
    'short ID' => 'short_id',
    'abstract' => 'abstract',
    'access url' => 'website_url',
    'closed' => 'closed_group',
    'contact information' => 'contact',
    'content creation' => 'content_creation',
    'creation date' => 'created',
    'description' => 'description',
    'featured' => 'site_featured',
    'keywords' => 'field_keywords',
    'logo' => 'logo',
    'moderation' => 'group_moderation',
    'modification date' => 'changed',
    'owner' => 'owner',
    'published at' => 'published_at',
    'topic' => 'field_topic',
    'spatial coverage' => 'spatial_coverage',
    'state' => 'field_state',
  ];

  /**
   * Returns value mapping of human-friendly field values.
   *
   * Value keys are the human-friendly values used in the cucumber tables, their
   * values are the actual field values to apply. Every value alias - value list
   * is keyed by the un-aliased field name.
   */
  protected function getValueMapping(): array {
    $mapping = [
      'group_moderation' => ['no' => 0, 'yes' => 1],
      'content_creation' => [
        'facilitators and authors' => ContentCreationOptions::FACILITATORS_AND_AUTHORS,
        'members' => ContentCreationOptions::MEMBERS,
        'registered users' => ContentCreationOptions::REGISTERED_USERS,
      ],
      'closed_group' => ['no' => 0, 'yes' => 1],
      'field_state' => [
        'draft' => 'draft',
        'proposed' => 'proposed',
        'published' => 'published',
        'archival request' => 'archival_request',
        'archived' => 'archived',
      ],
    ];

    Assert::assertEmpty(
      array_diff(
        array_keys($mapping),
        static::FIELD_ALIASES,
      ),
    );

    return $mapping;
  }

  /**
   * Test collections.
   *
   * @var \Drupal\collection\Entity\CollectionInterface[]
   */
  protected array $collections = [];

  /**
   * Navigates to the propose collection form.
   *
   * @When I go to the propose collection form
   */
  public function visitProposeCollectionForm(): void {
    $this->visitPath('propose/collection');
  }

  /**
   * Navigates to the canonical page display of a collection.
   *
   * @param string $collection
   *   The title of the collection.
   *
   * @When I go to (the homepage of )the :collection collection
   * @When I visit (the homepage of )the :collection collection
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitCollection(string $collection): void {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->getEntityByLabel('node', $collection, 'collection');
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Checks that the user is on the given collection homepage.
   *
   * @param string $collection
   *   The title of the collection.
   *
   * @Then I should be on (the homepage of )the :collection collection
   */
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
   *
   * @When I visit the collection overview( page)
   */
  public function visitCollectionOverviewPage(): void {
    $this->visitPath('/collections');
  }

  /**
   * Creates a number of collections with data provided in a table.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | title                   | abstract                                   | access url                             | closed | creation date    | description                                                                                                        | content creation                                  | featured | logo | moderation | modification date | owner | state                                              |
   * | Dog owner collection    | Read up on all about <strong>dogs</strong> | http://dogtime.com/dog-breeds/profiles | yes|no | 28-01-1995 12:05 | The Afghan Hound is elegance personified.                                                                          | facilitators and authors|members|registered users | yes      |      | yes        |                   |       |                                                    |
   * | Cats collection 4 ever! | Cats are cool!                             | http://mashable.com/category/cats/     | yes|no | 28-01-1995 12:06 | The domestic cat (Felis catus or Felis silvestris catus) is a small usually furry domesticated carnivorous mammal. | facilitators and authors|members|registered users | no       |      | no         |                   |       | draft|proposed|published|archival request|archived |
   * @codingStandardsIgnoreEnd
   *
   * Only the title field is required.
   *
   * @param \Behat\Gherkin\Node\TableNode $collection_table
   *   The collection data.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )collections:
   */
  public function givenCollections(TableNode $collection_table): void {
    $aliases = self::FIELD_ALIASES;

    foreach ($collection_table->getColumnsHash() as $collection) {
      $values = [];
      // Replace the column aliases with the actual field names.
      foreach ($collection as $key => $value) {
        if (array_key_exists($key, $aliases)) {
          $values[$aliases[$key]] = $value;
        }
        else {
          throw new \Exception("Unknown column '$key' in collection table.");
        }
      }

      // Convert user friendly values to machine values.
      $values = $this->convertValueAliases($values);

      // Provide default values.
      $values = $this->provideDefaultValues($values);

      $this->createCollection($values);
    }
  }

  /**
   * Enriches the provided field values with defaults for missing values.
   *
   * @param array $fields
   *   An array of fields keyed by field name.
   *
   * @return array
   *   The array with default values added.
   */
  protected function provideDefaultValues(array $fields): array {
    $default_values = [
      'field_state' => 'draft',
      'abstract' => 'This is a test abstract.',
    ];

    foreach ($default_values as $field => $default_value) {
      if (!isset($fields[$field])) {
        $fields[$field] = $default_value;
      }
    }

    return $fields;
  }

  /**
   * Converts values from user friendly to normal machine values.
   *
   * @param array $fields
   *   An array of fields keyed by field name.
   *
   * @return array
   *   The array with the values converted.
   *
   * @throws \Exception
   *    Throws an exception when a mapped value is not found.
   */
  protected function convertValueAliases(array $fields): array {
    $mapped_values = $this->getValueMapping();

    foreach ($fields as $field => $value) {
      if (isset($mapped_values[$field])) {
        if (!isset($mapped_values[$field][$value])) {
          throw new \Exception("Value $value is not an acceptable value for field $field.");
        }

        $fields[$field] = $mapped_values[$field][$value];
      }
    }

    // Convert any entity reference field label value with the entity id.
    $fields = $this->convertEntityReferencesValues('node', 'collection', $this->parseRdfEntityFields($fields));

    return $fields;
  }

  /**
   * Creates a collection from the given property and field data.
   *
   * @param array $values
   *   An optional associative array of values, keyed by property name.
   *
   * @return \Drupal\collection\Entity\CollectionInterface
   *   A new collection entity.
   *
   * @throws \Exception
   *   Thrown when a given image is not found.
   */
  protected function createCollection(array $values): CollectionInterface {
    // Add logo image.
    if (!empty($values['logo'])) {
      $values['logo'] = [$this->createFile($values['logo'])->id()];
    }

    // If the collection is featured we need to create the meta entity that
    // stores this information after creating the collection.
    $is_featured = in_array(strtolower((string) ($values['feature'] ?? '')), [
      'y',
      'yes',
    ]);

    $this->setFallbackTopic($values);

    /** @var \Drupal\collection\Entity\CollectionInterface $collection */
    $collection = $this->createNode('collection', $values);

    if ($is_featured) {
      $collection->feature();
    }

    $this->collections[$collection->id()] = $collection;

    return $collection;
  }

  /**
   * Creates a collection with data provided in a table.
   *
   * @codingStandardsIgnoreStart
   * Table format:
   * | title            | Open Data Initiative                               |
   * | author           | Mightily Oats                                      |
   * | logo             | logo.png                                           |
   * | featured         | yes|no                                             |
   * | moderation       | yes|no                                             |
   * | closed           | yes|no                                             |
   * | content creation | facilitators and authors|members|registered users  |
   * | metadata url     | https://ec.europa.eu/my/url                        |
   * | state            | draft|proposed|published|archival request|archived |
   * @codingStandardsIgnoreEnd
   *
   * Only the title field is required.
   *
   * @param \Behat\Gherkin\Node\TableNode $collection_table
   *   The collection data.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )collection:
   */
  public function givenCollection(TableNode $collection_table): void {
    $aliases = self::FIELD_ALIASES;

    $values = [];
    // Replace the column aliases with the actual field names.
    foreach ($collection_table->getRowsHash() as $key => $value) {
      if (array_key_exists($key, $aliases)) {
        $values[$aliases[$key]] = $value;
      }
      else {
        throw new \Exception("Unknown column '$key' in collection table.");
      }
    }

    // Convert user friendly values to machine values.
    $values = $this->convertValueAliases($values);

    // Provide default values.
    $values = $this->provideDefaultValues($values);

    $this->createCollection($values);
  }

  /**
   * Deletes a collection.
   *
   * @param string $collection
   *   The title of the collection.
   *
   * @When I delete the :collection collection
   */
  public function deleteCollection(string $collection): void {
    $collection = $this->getEntityByLabel('node', $collection);
    $collection->skip_notification = TRUE;
    $collection->delete();
  }

  /**
   * Checks the number of available collections.
   *
   * @param int $number
   *   The expected number of collections.
   *
   * @Then I should have :number collection(s)
   */
  public function assertCollectionCount(int $number): void {
    $this->assertEntityCount('node', 'collection', $number);
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
   *
   * @Given (the following )collection user membership(s):
   */
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
   *
   * @Given (the following )custom page(s) :menu_type menu structure for the :group_label :group_bundle:
   */
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
   *
   * @Given I should own the :entity collection
   */
  public function assertCollectionOwnershipCurrentUser(string $entity): void {
    if (!$current_user = $this->userManager->getCurrentUser()) {
      throw new \Exception("No current user.");
    }
    $this->assertCollectionOwnership($current_user->name, $entity);
  }

  /**
   * Removes any created collections.
   *
   * @AfterScenario @api
   */
  public function cleanCollections(): void {
    if (empty($this->collections)) {
      return;
    }

    // Remove any collections that were created.
    foreach ($this->collections as $collection) {
      $collection->skip_notification = TRUE;
      $collection->delete();
    }
    $this->collections = [];
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
   *
   * @Then for the following collection, the corresponding user should have the corresponding (available )state buttons:
   */
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
      $this->visitEntityForm('edit', $values['collection'], 'collection');
      $buttons = $this->explodeCommaSeparatedStepArgument($values['buttons']);
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
   *
   * @Then the visibility of the delete link should be as follows for these users in these collections:
   */
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
   *
   * @Given I am about to leave( the) :label( collection)
   */
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
   *
   * @Then I am member of :labels collection(s)
   * @Then user :user_name is member of :labels collection(s)
   */
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
    foreach ($this->explodeCommaSeparatedStepArgument($labels) as $label) {
      /** @var \Drupal\collection\Entity\CollectionInterface $collection */
      $collection = $this->getEntityByLabel('node', $label, 'collection');
      if (!$collection->getMembership((int) $account->id(), [])) {
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
   *
   * @When I change the name of the :collection collection to :name
   */
  public function updateCollectionName(string $collection, string $name): void {
    $entity = $this->getEntityByLabel('node', $collection);
    $entity->setName($name)->save();
  }

  /**
   * Creates the standard 'Joinup' collection.
   *
   * @return \Drupal\collection\Entity\CollectionInterface
   *   The 'Joinup' collection.
   *
   * @BeforeScenario @joinup_collection&&@api
   *
   * @see joinup_collection.module
   */
  public function createJoinupCollection(): CollectionInterface {
    return $this->createCollection([
      'id' => JoinupCollectionHelper::getCollectionId(),
      'label' => 'Joinup',
      'field_ar_state' => 'published',
    ]);
  }

  /**
   * Asserts that a glossary navigator is present or not on the page.
   *
   * @param string|null $expected_navigator
   *   The navigator links.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   The navigator is missing from the page.
   *
   * @Then I should see the glossary navigator :expected_navigator
   * @Then I should not see the glossary navigator
   */
  public function assertGlossaryNavigator(?string $expected_navigator = NULL): void {
    $page = $this->getSession()->getPage();
    $navigator_node = $page->find('css', '.glossary-navigator');

    if (!$expected_navigator) {
      if ($navigator_node) {
        throw new ExpectationFailedException("The glossary navigator exists on the page but it should not.");
      }
      return;
    }

    if (!$navigator_node) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'navigator');
    }

    $actual_navigator = $navigator_node->getText();
    if ($actual_navigator !== $expected_navigator) {
      throw new ExpectationFailedException("Expected navigator '{$expected_navigator}' but found '{$actual_navigator}'.");
    }
  }

  /**
   * Sets a random collection on content that requires one.
   *
   * Some tests deal with collection content but don't care about which
   * collection the content belongs to. It is tedious to manually define a
   * collection and link it to the content for every single test. If it is
   * omitted in the definition of the test we assign an existing collection, or
   * if none exist we assign the "Joinup" collection. This ensures data
   * integrity and prevents form validation errors.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   An object containing the entity properties and fields that are to be used
   *   for creating the node as properties on the object.
   *
   * @BeforeNodeCreate
   */
  public function provideCollection(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();

    // Only deal with collection content.
    $collection_content_bundles = array_merge(['custom_page'], CommunityContentHelper::BUNDLES);
    if (!in_array($node->type, $collection_content_bundles, TRUE)) {
      return;
    }

    // Skip if a collection or solution has been defined.
    if (!empty($node->collection) || !empty($node->solution)) {
      return;
    }

    // Use an existing published collection if one is available.
    foreach ($this->collections as $candidate_collection) {
      if ($candidate_collection instanceof CollectionInterface && $candidate_collection->isPublished()) {
        $collection = $candidate_collection;
        break;
      }
    }

    // If no suitable candidate is found, use the default "Joinup" collection.
    if (empty($collection)) {
      $collection = $this->createJoinupCollection();
    }

    $node->collection = $collection->label();
  }

  /**
   * Creates and saves an node of a specific bundle.
   *
   * @param string $bundle
   *   The group entity bundle.
   * @param array $values
   *   An array of field values.
   *
   * @return \Drupal\node\NodeInterface
   *   The newly created entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the entity cannot be saved.
   */
  protected static function createNode(string $bundle, array $values): NodeInterface {
    foreach (['changed', 'created', 'published_at'] as $field) {
      if (isset($values[$field])) {
        $date = new DrupalDateTime($values[$field]);
        $values[$field] = $date->getTimestamp();
      }
    }

    $values['type'] = $bundle;
    $entity = Node::create($values);
    $entity->save();

    return $entity;
  }

  /**
   * Replaces collection field aliases in the solution node object.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   *
   * @BeforeNodeCreate
   */
  public function convertCollectionFieldAliases(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'collection') {
      return;
    }
    $this->convertFieldAliases($node, self::FIELD_ALIASES);
  }

  /**
   * Adds a default logo image to the collection if it was not defined.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   *
   * @BeforeNodeCreate
   */
  public function ensureCollectionLogo(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'collection') {
      return;
    }
    // Add logo image.
    if (!empty($node->logo)) {
      $node->logo = $this->getFileSourcePath($node->logo);
    }
  }

  /**
   * Converts values from user-friendly to normal machine values.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   *
   * @BeforeNodeCreate
   */
  public function convertCollectionValueAliases(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'collection') {
      return;
    }
    $value_mapping = $this->getValueMapping();
    foreach ((array) $node as $field => $value) {
      if (!isset($value_mapping[$field])) {
        continue;
      }
      Assert::assertArrayHasKey(
        $value,
        $value_mapping[$field],
        sprintf(
          "Value '%s' is not an acceptable value for collection field %s.",
          $value,
          $field,
        )
      );

      $node->$field = $value_mapping[$field][$value];
    }
  }

  /**
   * Converts the value of collection link fields to the expected format.
   *
   * \Drupal\Driver\Fields\Drupal8\LinkHandler expects multi-column link field
   * values; this is a workaround for a not-yet-applied patch we have in the
   * OSS catalogue EPIC branch.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   *
   * @BeforeNodeCreate
   */
  public function convertLinkFields(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'collection') {
      return;
    }
    $entityFieldManager = \Drupal::service('entity_field.manager');
    assert($entityFieldManager instanceof EntityFieldManagerInterface);
    $fields = $entityFieldManager->getFieldDefinitions('node', 'collection');

    foreach ((array) $node as $field => $value) {
      if ($fields[$field]->getType() !== 'link') {
        continue;
      }

      if (strpos($value, ' - ') || strpos($field, ':')) {
        continue;
      }

      // Column marker is ':', key-value separator is ' - '.
      // See RawDrupalContext::parseEntityFields().
      $node->$field = "0:  - 1: $value";
    }
  }

}
