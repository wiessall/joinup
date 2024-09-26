<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\FormTrait;
use Drupal\joinup\Traits\OgTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\TopicTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup\Traits\WorkflowTrait;
use Drupal\joinup_group\ContentCreationOptions;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\PinnableGroupContentInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og\OgRoleInterface;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for testing solutions.
 */
class SolutionContext extends RawDrupalContext {

  use EntityReferenceTrait;
  use EntityTrait;
  use FileTrait;
  use FormTrait;
  use OgTrait;
  use RdfEntityTrait;
  use TestingEntitiesTrait;
  use SearchTrait;
  use TopicTrait;
  use TraversingTrait;
  use UserTrait;
  use UtilityTrait;
  use WorkflowTrait;

  /**
   * Navigates to the add solution form.
   *
   * @param string $collection
   *   The parent collection.
   *
   * @When I go to the add solution form of the :collection collection
   * @When I visit the add solution form of the :collection collection
   */
  public function visitAddSolutionForm(string $collection): void {
    $collection = $this->getEntityByLabel('node', $collection);
    $solution_url = Url::fromRoute('joinup_solution.collection_solution.add', [
      'node' => $collection->id(),
    ]);

    $this->visitPath($solution_url->getInternalPath());
  }

  /**
   * Navigates to the canonical page display of a solution.
   *
   * @param string $solution
   *   The name of the solution.
   *
   * @When I go to (the homepage of )the :solution solution
   * @When I visit (the homepage of )the :solution solution
   * @Given I am on (the homepage of )the :solution solution
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitSolution(string $solution): void {
    /** @var \Drupal\joinup_solution\Entity\SolutionInterface $entity */
    $entity = $this->getEntityByLabel('node', $solution);
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Navigates to the solution overview page.
   *
   * @todo This is currently dependent on the Joinup profile being installed,
   *   since the view providing this overview page is exported in the profile.
   *   Remove this todo when ISAICP-5176 is fixed.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-5176
   *
   * @When I visit the solution overview( page)
   */
  public function visitSolutionOverviewPage(): void {
    $this->visitPath('/solutions');
  }

  /**
   * Changes a value of a solution by API.
   *
   * @param string $fieldName
   *   The alias of the solution field.
   * @param string $label
   *   The label of the solution entity.
   * @param string $fieldValue
   *   The new value of the solution field.
   *
   * @Given the :fieldName of :label solution is changed to :fieldValue
   */
  public function givenSolutionNewValue(string $fieldName, string $label, string $fieldValue): void {
    $fieldName = JoinupContext::getNodeFieldNameFromAlias('solution', $fieldName);
    $solution = static::getEntityByLabel('node', $label, 'solution');
    Assert::assertInstanceOf(SolutionInterface::class, $solution, "Cannot find any solution with label $label");
    $fieldManager = \Drupal::service('entity_field.manager');
    $fieldDefinitions = $fieldManager->getFieldDefinitions('node', 'solution');
    Assert::assertArrayHasKey($fieldName, $fieldDefinitions, "Field $fieldName does not exist in the solution entity");

    // For reference fields, we need to convert the value to the entity ID.
    if ($fieldDefinitions[$fieldName]->getType() === 'entity_reference') {
      $targetType = $fieldDefinitions[$fieldName]->getSetting('target_type');
      $entity = $this->getEntityByLabel($targetType, $fieldValue);
      $fieldValue = $entity->id();
    }

    $solution->set($fieldName, $fieldValue);
    Assert::assertSame(SAVED_UPDATED, $solution->save(), "Solution cannot be updated");
  }

  /**
   * Asserts that a user is an owner of the given solution.
   *
   * To be owner of a solution, a user should be an administrator and
   * a facilitator.
   *
   * @param string $username
   *   The name of the user.
   * @param string $solution
   *   The label of the group entity.
   *
   * @throws \Exception
   *    Throws an exception when the user is not found.
   *
   * @Given (the user ):username should be the owner of the :solution solution
   */
  public function assertSolutionOwnership(string $username, string $solution): void {
    $user = user_load_by_name($username);
    if (empty($user)) {
      throw new \Exception("User $username could not be found.");
    }

    $solution = $this->getEntityByLabel('node', $solution, 'solution');
    $owner_roles = [
      OgRoleInterface::ADMINISTRATOR,
      'facilitator',
    ];

    $this->assertOgGroupOwnership($user, $solution, $owner_roles);
  }

  /**
   * Applies post-save tasks: Pinning and unpinning.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope $scope
   *   The Behat hook scope object containing the metadata of the node that was
   *   created.
   *
   * @AfterNodeCreate
   */
  public function afterNodeCreateHandlePinning(AfterNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'solution') {
      return;
    }

    if (!isset($node->pinned_in_collection) && !isset($node->unpinned_in_collection)) {
      return;
    }

    if (($entity = Node::load((int) $node->nid)) && $entity instanceof PinnableGroupContentInterface) {
      // Pin solution in collection where needed.
      foreach (explode(',', $node->pinned_in_collection ?? '') as $groupLabel) {
        if (!($groupLabel = trim($groupLabel))) {
          continue;
        }
        $group = $this->getEntityByLabel('node', $groupLabel);
        Assert::assertInstanceOf(
          GroupInterface::class,
          $group,
          "Cannot pin solution {$entity->label()} in $groupLabel since it does not exist or is not a group."
        );
        $entity->pin($group);
      }
    }

    if (($entity = Node::load((int) $node->nid)) && $entity instanceof PinnableGroupContentInterface) {
      // Pin solution in collection where needed.
      foreach (explode(',', $node->unpinned_in_collection ?? '') as $groupLabel) {
        if (!($groupLabel = trim($groupLabel))) {
          continue;
        }
        $group = $this->getEntityByLabel('node', $groupLabel);
        Assert::assertInstanceOf(
          GroupInterface::class,
          $group,
          "Cannot pin solution {$entity->label()} in $groupLabel since it does not exist or is not a group."
        );
        $entity->unpin($group);
      }
    }
  }

  /**
   * Deletes a solution.
   *
   * @param string $solution
   *   The name of the solution.
   *
   * @When I delete the :solution solution
   */
  public function deleteSolution(string $solution): void {
    $solution = $this->getEntityByLabel('node', $solution);
    $solution->skip_notification = TRUE;
    $solution->delete();
  }

  /**
   * Deletes a solution document's files.
   *
   * @param string $solution
   *   The name of the solution.
   *
   * @When I delete documents from :solution solution
   */
  public function deleteDocumentsFromSolution(string $solution): void {
    $solution = $this->getEntityByLabel('node', $solution);

    /** @var \Drupal\file_url\FileUrlHandler $fileHandler */
    $fileHandler = \Drupal::service('file_url.handler');

    $values = $solution->get('documentation')->getValue();
    foreach ($values as $value) {
      $file = $fileHandler::urlToFile($value['target_id']);
      if (!$file || $fileHandler::isRemote($file)) {
        continue;
      }

      $file->delete();
    }
  }

  /**
   * Subscribes the given users to the given solution.
   *
   * The role Id of organic group roles are consisting of 3 parts, the entity
   * type ID and the bundle of the group, and the machine name of the role.
   * Do not provide the complete ID of the Og role. The entity type ID and
   * bundle are going to be added automatically.
   *
   * Table format:
   * | solution          | user | roles                      | state   |
   * | Sample solution A | John | facilitator, administrator | active  |
   * | Sample solution B | Jack |                            | blocked |
   *
   * Only the 'solution' and 'user' columns are required. Multiple comma
   * separated roles can be passed.
   *
   * The state can be empty, or one of 'active', 'pending', or 'blocked'. If the
   * state is omitted it will default to 'active'.
   *
   * @param \Behat\Gherkin\Node\TableNode $membership_table
   *   The membership table.
   *
   * @throws \Exception
   *   Thrown when a solution is not found.
   *
   * @Given (the following )solution user membership(s):
   */
  public function givenSolutionUserMemberships(TableNode $membership_table): void {
    foreach ($membership_table->getColumnsHash() as $values) {
      $group = $this->getEntityByLabel('node', $values['solution']);
      $this->givenUserMembership($group, $values);
    }
  }

  /**
   * Checks that a user has the available state options for the solution.
   *
   * The method also checks that these options are the only options available.
   *
   * This method will log in as each user in sequence, so take care to only use
   * it when the currently logged-in user can be discarded.
   *
   * Table format:
   * | solution   | user | buttons         |
   * | Solution A | John | Save as draft   |
   * | Solution B | Jack | Update, Publish |
   *
   * @param \Behat\Gherkin\Node\TableNode $check_table
   *   The table with the triplets solution-user-buttons.
   *
   * @throws \Exception
   *    Thrown when the user does not exist.
   *
   * @todo Maybe there is a better definition available here like 'The
   * following state buttons should be available for the user on the
   * solution.
   *
   * @Then for the following solution, the corresponding user should have the corresponding (available )state buttons:
   */
  public function verifyWorkflowStates(TableNode $check_table): void {
    foreach ($check_table->getColumnsHash() as $values) {
      $username = $values['user'];
      $user = $this->userManager->getUser($username);

      // Check if we are already logged in as the user to test.
      $current_username = $this->userManager->getCurrentUser()->name ?? NULL;
      if ($username !== $current_username) {
        $this->authenticationManager->logIn($user);
      }

      // Go to the edit form and check that the expected buttons are visible.
      $this->visitEntityForm('edit', $values['solution'], 'solution');
      $buttons = $this->explodeCommaSeparatedStepArgument($values['buttons']);
      $this->assertSubmitButtonsVisible($buttons);
    }
  }

  /**
   * Checks that a user has access to the delete button on the solution form.
   *
   * Table format:
   * | solution   | user | delete link |
   * | Solution A | John | yes         |
   * | Solution B | Jack | no          |
   *
   * @param \Behat\Gherkin\Node\TableNode $check_table
   *   The table with the triplets solution-user-link visibility.
   *
   * @throws \Exception
   *    Thrown when the user does not exist.
   *
   * @Then the visibility of the delete link should be as follows for these users in these solutions:
   */
  public function verifyDeleteLinkVisibility(TableNode $check_table): void {
    foreach ($check_table->getColumnsHash() as $values) {
      $user = $this->getUserByName($values['user']);
      $solution = $this->getEntityByLabel('node', $values['solution']);
      $visible = $values['delete link'] === 'yes';
      $this->assertGroupEntityOperation($visible, 'delete', $solution, $user);
    }
  }

  /**
   * Enable a given language.
   *
   * @param string $langcode
   *   Language code.
   *
   * @Given the language :langcode is enabled
   */
  public function languageEnabled(string $langcode): void {
    // Create the needed language.
    $language_manager = \Drupal::languageManager();
    if (!$language_manager->getLanguage($langcode)) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
  }

  /**
   * Creates a simple multilingual solution.
   *
   * @Given the multilingual :title solution of :collection collection
   */
  public function theMultilingualSolution(string $title, string $collection): void {
    $collection = $this->getEntityByLabel('node', $collection, 'collection');
    $values = [
      'label' => $title,
      'field_is_state' => 'published',
      'field_is_description' => "English description",
      'collection' => $collection->id(),
    ];
    $solution = $this->createRdfEntity('solution', $values);
    // Fill with the specific content translation fields and fall-back to
    // the remaining values from the base translation.
    $solution_values = $solution->toArray();
    $solution_values += [
      'content_translation_source' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'content_translation_status' => TRUE,
      'content_translation_uid' => 1,
      'content_translation_outdated' => FALSE,
    ];
    $solution_values['field_is_description'] = "Catalan description";
    // Create the translation.
    $solution->addTranslation('ca', $solution_values)->save();
    $this->entities['node'][$solution->id()] = $solution;
  }

  /**
   * Checks that the given tile contains the given number of downloads.
   *
   * @param string $heading
   *   Heading of the tile.
   * @param int $count
   *   Number of the downloads.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown when the tile is not found in the page.
   *
   * @Then the :heading tile should show :count downloads
   */
  public function assertTileContainsDownloadCount(string $heading, int $count): void {
    $tile = $this->getTileByHeading($heading);
    $elements = $tile->findAll('xpath', "//span[contains(text(), '$count')]");
    Assert::assertCount(1, $elements);
  }

  /**
   * Checks that the given tile contains a download icon.
   *
   * @param string $heading
   *   Heading of the tile.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown when the tile is not found in the page.
   *
   * @Then the download icon should not be shown in the :heading tile
   */
  public function assertTileNotContainsDownloadIcon(string $heading): void {
    $tile = $this->getTileByHeading($heading);
    $elements = $tile->findAll('xpath', '//span[contains(concat(" ", normalize-space(@class), " "), " icon--download ")]');
    Assert::assertEmpty($elements);
  }

  /**
   * Converts values from user-friendly to normal machine values.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   *
   * @BeforeNodeCreate
   */
  public function convertSolutionValueAliases(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'solution') {
      return;
    }

    $value_mapping = [
      'group_moderation' => ['no' => 0, 'yes' => 1],
      'content_creation' => [
        'facilitators and authors' => ContentCreationOptions::FACILITATORS_AND_AUTHORS,
        'registered users' => ContentCreationOptions::REGISTERED_USERS,
      ],
      'field_is_webdav_creation' => ['no' => 0, 'yes' => 1],
      'list_related_entities' => ['no' => 0, 'yes' => 1],
    ];

    foreach ((array) $node as $field => $value) {
      if (!isset($value_mapping[$field])) {
        continue;
      }
      Assert::assertArrayHasKey(
        $value,
        $value_mapping[$field],
        sprintf(
          "Value '%s' is not an acceptable value for solution field %s.",
          $value,
          $field,
        )
      );

      $node->$field = $value_mapping[$field][$value];
    }
  }

  /**
   * Ensures that the solution being created has a parent collection.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   *
   * @BeforeNodeCreate
   */
  public function ensureSolutionParentCollection(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'solution') {
      return;
    }
    if (empty($node->og_audience)) {
      $label = $this->getRandom()->sentences(3);
      // We must use \Drupal\joinup\Context\DrupalContext to create the
      // missing collection, since entities are removed per context class. If
      // we do it directly in this context, then the solution was deleted after
      // the collection which triggered destruction issues in RDF syncronizer.
      $environment = $scope->getEnvironment();
      $context = $environment->getContext(DrupalContext::class);
      Assert::assertInstanceOf(DrupalContext::class, $context);
      $collection = $context->nodeCreate((object) [
        'type' => 'collection',
        'title' => $label,
        // Collection context will ensure that there is a state property set to
        // draft. We set the state here to allow joinup context to convert it
        // to a field properly.
        // @see \Drupal\joinup\Context\CollectionContext::defaultCollectionValues
        // @see \Drupal\joinup\Context\JoinupContext::massageFieldsBeforeNodeCreate
        'state' => 'published',
      ]);
      $node->og_audience = $collection->nid;

      // Remove any links created by this collection because they might produce
      // name collisions with the solution. E.g. the left sidebar link 'About'.
      // @todo Remove this workaround in ISAICP-5597.
      // @see tests/features/custom_page/navigation_menu.feature
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-5597
      $entity_type_manager = \Drupal::entityTypeManager();
      $storage = $entity_type_manager->getStorage('ogmenu_instance');
      $og_menu_instance_ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'navigation')
        ->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $collection->nid)
        ->execute();
      $storage->delete($storage->loadMultiple($og_menu_instance_ids));
    }
  }

}
