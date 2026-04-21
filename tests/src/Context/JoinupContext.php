<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\joinup\Traits\BootstrapTrait;
use Drupal\joinup\Traits\ContextualLinksTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\FormTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\KeyboardInteractionTrait;
use Drupal\joinup\Traits\NodeTrait;
use Drupal\joinup\Traits\OgTrait;
use Drupal\joinup\Traits\RandomGeneratorTrait;
use Drupal\joinup\Traits\TabledragTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup\Traits\WorkflowTrait;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\PinnableGroupContentInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og\OgRoleInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use GuzzleHttp\Client;

/**
 * Behat step definitions that are generally useful for the Joinup project.
 */
class JoinupContext extends RawDrupalContext {

  use BootstrapTrait;
  use ContextualLinksTrait;
  use EntityTrait;
  use FileTrait;
  use FormTrait;
  use JavascriptTrait;
  use KeyboardInteractionTrait;
  use OgTrait;
  use NodeTrait;
  use RandomGeneratorTrait;
  use StringTranslationTrait;
  use TabledragTrait;
  use TestingEntitiesTrait;
  use TraversingTrait;
  use UserTrait;
  use UtilityTrait;
  use WorkflowTrait;

  /**
   * Creates a user with data provided in a table.
   *
   * Table format:
   * | Username   | Mr Bond            |
   * | Password   | Bond007            |
   * | E-mail     | james.bond@mi5.org |
   *
   * @param \Behat\Gherkin\Node\TableNode $user_table
   *   The user data.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   */
  #[Given('(the following )user:')]
  public function givenUser(TableNode $user_table): void {
    $this->createUser($user_table->getRowsHash());
  }

  /**
   * Asserts that the given html exists in the page.
   *
   * @param string $text
   *   The html that is expected to be found.
   */
  #[Then('the page should contain the html text :text')]
  public function assertHtmlText($text): void {
    $actual = $this->getSession()->getPage()->getHtml();
    $regex = '/' . preg_quote($text, '/') . '/ui';
    $message = sprintf('The text "%s" was not found anywhere in the text of the current page.', $text);

    $this->assertCondition((bool) preg_match($regex, $actual), $message);
  }

  /**
   * Checks that a given text appears a certain number of times.
   *
   * @param string $text
   *   The text that should have a particular number of appearances.
   * @param int $count
   *   The number of appearances.
   */
  #[Then('the text :text should appear :count time(s)')]
  public function assertTextCount(string $text, int $count): void {
    $xpath = '//*[contains(text(), "' . $text . '")]';
    $this->assertSession()->elementsCount('xpath', $xpath, $count);
  }

  /**
   * Creates and authenticates a user with the given og role(s).
   *
   * Multiple roles can be passed separated with comma.
   *
   * @param string $roles
   *   A comma separated list of roles to assign to the user.
   * @param string $group_label
   *   The label of the collection or solution of which the user is a member.
   * @param string $group_bundle
   *   The RDF entity bundle, either 'collection' or 'solution'.
   *
   * @throws \Exception
   *   Thrown when a collection or solution with the given label doesn't exist.
   *
   * @see \Drupal\DrupalExtension\Context\DrupalContext::assertAuthenticatedByRole()
   */
  #[Given('I am logged in as a user with the :role role(s) of the :group_label :group_bundle')]
  #[Given('I am logged in as a/an :role of the :group_label :group_bundle')]
  public function assertAuthenticatedByOgRole(string $roles, string $group_label, string $group_bundle): void {
    $entity = $this->getEntityByLabel('node', $group_label, $group_bundle);
    if (!$entity) {
      throw new \Exception("No entity found with label $group_label");
    }
    $roles = $this->explodeDelimitedSeparatedStepArgument($roles);
    $roles = $this->getOgRoles($roles, $entity);

    // Check if a user with this role is already logged in.
    if (!$this->loggedInWithOgRoles($roles, $entity)) {
      $random = $this->getRandom()->name(8);
      // Create user (and project)
      $user = (object) [
        'name' => $random,
        'pass' => $random,
      ];
      $user->mail = "{$user->name}@example.com";
      $this->userCreate($user);

      // Load the actual user account.
      $account = User::load($user->uid);
      $this->subscribeUserToGroup($account, $entity, $roles);

      // Login.
      $this->login($user);
    }
  }

  /**
   * Changes the role of a user within a collection or solution.
   *
   * Use this to e.g. test promotion or demotion of facilitators.
   *
   * @param string $group_label
   *   The label of the collection or solution of which the user is a member.
   * @param string $group_bundle
   *   The RDF entity bundle, either 'collection' or 'solution'.
   * @param string $roles
   *   A comma separated list of roles to assign to the user. All previously
   *   assigned roles will be discarded.
   *
   * @throws \Exception
   *   Thrown when a collection or solution with the given label doesn't exist.
   */
  #[Given('my role(s) in the :group_label :group_bundle change(s) to :roles')]
  public function updateGroupRoles(string $group_label, string $group_bundle, string $roles): void {
    $entity = $this->getEntityByLabel('node', $group_label, $group_bundle);
    if (!$entity) {
      throw new \Exception("No entity found with label $group_label");
    }
    $roles = $this->explodeDelimitedSeparatedStepArgument($roles);
    $roles = $this->getOgRoles($roles, $entity);

    $current_user = $this->getUserManager()->getCurrentUser();
    $account = User::load($current_user->uid);

    $this->subscribeUserToGroup($account, $entity, $roles);
  }

  /**
   * Changes the membership state of a user within a collection or solution.
   *
   * Use this to e.g. test approval of pending memberships, or blocking users.
   *
   * @param string $group_label
   *   The label of the collection or solution of which the user is a member.
   * @param string $group_bundle
   *   The RDF entity bundle, either 'collection' or 'solution'.
   * @param string $state
   *   The new membership state, one of 'active', 'pending' or 'blocked'.
   *
   * @throws \Exception
   *   Thrown when a collection or solution with the given label doesn't exist.
   */
  #[Given('my membership state in the :group_label :group_bundle changes to :state')]
  public function updateGroupState(string $group_label, string $group_bundle, string $state): void {
    $entity = $this->getEntityByLabel('node', $group_label, $group_bundle);
    if (!$entity) {
      throw new \Exception("No entity found with label $group_label");
    }

    $current_user = $this->getUserManager()->getCurrentUser();
    $account = User::load($current_user->uid);

    $this->subscribeUserToGroup($account, $entity, [], $state);
  }

  /**
   * Checks that the logged in user has the given OG roles in the given group.
   *
   * If the user has more than the required roles, they might have permissions
   * from the rest of the roles that will lead the test to a false positive.
   * For this reason, we request check for the specific roles passed.
   *
   * @param \Drupal\og\Entity\OgRole[] $roles
   *   An array of roles to check.
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group that is checked if the user has the role.
   *
   * @return bool
   *   Returns TRUE if the current logged in user has this role (or roles).
   *
   * @see \Drupal\DrupalExtension\Context\RawDrupalContext::loggedInWithRole
   */
  protected function loggedInWithOgRoles(array $roles, GroupInterface $group): bool {
    if ($this->getUserManager()->currentUserIsAnonymous() || !$this->loggedIn()) {
      return FALSE;
    }
    $current_user = $this->getUserManager()->getCurrentUser();
    $user = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged($current_user->uid);
    $membership = $group->getMembership($user->id());
    if (empty($membership)) {
      return FALSE;
    }
    $expected_roles_ids = array_map(function (OgRoleInterface $role): string {
      return $role->id();
    }, $roles);
    $actual_roles_ids = $membership->getRolesIds();

    sort($expected_roles_ids);
    sort($actual_roles_ids);

    return $expected_roles_ids === $actual_roles_ids;
  }

  /**
   * Checks that the given select field has the given options.
   *
   * @param string $select
   *   The name of the field element.
   * @param string $options
   *   The options to be checked separated by comma. Only labels are accepted.
   */
  #[Then('(the ):select field should contain the :options option(s)')]
  public function checkSelectContainsOptions($select, $options): void {
    $field = $this->findSelect($select);
    $available_options = array_map(function (array $option): string {
      return $option['text'];
    }, $this->getSelectOptions($field));
    $options = $this->explodeDelimitedSeparatedStepArgument($options);

    if (array_intersect($options, $available_options) !== $options) {
      throw new \Exception("Options are not included in select.");
    }
  }

  /**
   * Checks that the given select field has the given options.
   *
   * @param string $select
   *   The name of the field element.
   */
  #[Then('the :select select field options should be sorted alphabetically')]
  public function checkSelectOptionsSortedAlphabetically(string $select): void {
    $field = $this->findSelect($select);
    $available_options = $this->getSelectOptions($field);
    $sorted_options = $available_options;
    sort($sorted_options);

    $this->assertSameValues($available_options, $sorted_options);
  }

  /**
   * Checks if the given select field does not contain any of the given values.
   *
   * @param string $select
   *   The name of the field element.
   * @param string $options
   *   The options to be checked separated by comma.
   *
   * @throws \Exception
   *    Throws an exception when a value exists.
   */
  #[Then('(the ):select field should not contain the :options option(s)')]
  public function checkSelectDoesNotContainOptions($select, $options): void {
    $field = $this->findSelect($select);
    $available_options = array_map(function (array $option): string {
      return $option['text'];
    }, $this->getSelectOptions($field));
    $options = $this->explodeDelimitedSeparatedStepArgument($options);

    $intersection = array_intersect($available_options, $options);

    if (!empty($intersection)) {
      throw new \Exception("The select '{$select}' should not contain the options: " . implode(', ', $intersection));
    }
  }

  /**
   * Asserts the list of available options in a select box.
   *
   * @param string $select
   *   The name of the field element.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The available list of options.
   *
   * @throws \Exception
   *    Throws an exception when the select is not found or options are not
   *    identical.
   */
  #[Then('the :select select should contain the following options:')]
  public function assertSelectOptionsAsList($select, TableNode $table): void {
    $field = $this->findSelect($select);
    $this->assertSelectAvailableOptions($field, $table);
  }

  /**
   * Checks that a select field has exclusively the provided options.
   *
   * @param string $select
   *   The name of the select element.
   * @param string $options
   *   A comma-separated list of options to be present.
   */
  #[Then('the available options in the :select select should be :options')]
  public function assertSelectOptions($select, $options): void {
    $field = $this->findSelect($select);
    $availableOptions = array_map(function (array $option): string {
      return $option['text'];
    }, $this->getSelectOptions($field));
    sort($availableOptions);

    $options = $this->explodeDelimitedSeparatedStepArgument($options);
    sort($options);

    // The PHPUnit assertion will allow to understand easily which values are
    // missing and which one are extra.
    $this->assertSameValues($options, $availableOptions, "The '$select' select options don't match the wanted ones.");
  }

  /**
   * Asserts the available checkboxes options of a field.
   *
   * @param string $field
   *   The name of the field element.
   * @param \Behat\Gherkin\Node\TableNode $options
   *   The available list of options.
   */
  #[Then('the :field checkboxes should contain the following options:')]
  public function assertCheckboxesFieldOptions(string $field, TableNode $options): void {
    // We are looking for a field with the class 'field--widget-options-buttons'
    // that contains a fieldset and a legend with the $field as span.
    $xpath = "//fieldset[legend/span[text()='$field']]";
    $field = $this->getSession()->getPage()->find('xpath', $xpath);
    if (!$field) {
      throw new \Exception("The field '$field' was not found.");
    }

    // Get the checkboxes.
    $checkboxes = $field->findAll('css', 'input[type="checkbox"]');
    $checkboxLabels = array_map(function (NodeElement $checkbox): string {
      return $checkbox->getParent()->getText();
    }, $checkboxes);

    $expectedOptions = $options->getColumn(0);
    sort($expectedOptions);
    sort($checkboxLabels);

    $this->assertSameValues($expectedOptions, $checkboxLabels);
  }

  /**
   * Checks that a select field does not have any of the given options.
   *
   * @param string $select
   *   The name of the select element.
   * @param string $options
   *   A comma separated list of items to check.
   */
  #[Then('the available options in the :select select should not include the :options( options)')]
  public function assertSelectOptionNotAvailable($select, $options): void {
    $field = $this->findSelect($select);
    $availableOptions = array_map(function (array $option): string {
      return $option['text'];
    }, $this->getSelectOptions($field));
    $options = $this->explodeDelimitedSeparatedStepArgument($options);

    $this->assertCondition(empty(array_intersect($availableOptions, $options)), "The '$select' select options include at least one of the given values.");
  }

  /**
   * Checks that the given select field has the given optgroups.
   *
   * @param string $select
   *   The name of the field element.
   * @param string $optgroups
   *   The optgroups to be checked separated by comma.
   */
  #[Then('(the ):select field should contain the :optgroups option group(s)')]
  public function assertSelectContainsOptgroups($select, $optgroups): void {
    $field = $this->findSelect($select);
    $availableOptgroups = array_values($this->getSelectOptgroups($field));
    $options = $this->explodeDelimitedSeparatedStepArgument($optgroups);

    foreach ($options as $option) {
      $this->assertCondition(in_array($option, $availableOptgroups, TRUE), "The '$select' select doesn't contain the option '$option''.");
    }
  }

  /**
   * Navigates to the content overview, a.k.a. the "Keep up to date" page.
   */
  #[When('I visit the content overview page')]
  public function visitContentOverviewPage(): void {
    $this->visitPath('/keep-up-to-date');
  }

  /**
   * Navigates to the canonical page display of an entity.
   *
   * @param string $title
   *   The title of the entity.
   * @param string $bundle
   *   An alias of a bundle as defined in the
   *   \Drupal\joinup\Traits\NameMappingsTrait::bundleAliases method.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  #[Given('I go to (the homepage of )the :title :bundle')]
  #[Given('I visit (the homepage of )the :title :bundle')]
  #[Given('I am on (the homepage of )the :title :bundle')]
  public function visitCanonicalPage(string $title, string $bundle): void {
    $entityType = $this->translateEntityTypeAlias($bundle);
    $bundle = $this->translateBundle($bundle);
    $entity = $this->getEntityByLabel($entityType, $title, $bundle);
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Navigates to the edit or delete form of an entity.
   *
   * @param string $action
   *   The action. Either 'edit' or 'delete'.
   * @param string $title
   *   The title of the entity.
   * @param string $bundle
   *   An alias of a bundle as defined in the
   *   \Drupal\joinup\Traits\NameMappingsTrait::bundleAliases method.
   */
  #[When('I go to the :action form of the :title :bundle')]
  public function goToEntityForm(string $action, string $title, string $bundle): void {
    $entity_type_id = $this->translateEntityTypeAlias($bundle);
    $bundle = $this->translateBundle($bundle);
    $entity = $this->getEntityByLabel($entity_type_id, $title, $bundle);

    $rel = match ($action) {
      'edit' => 'edit-form',
      'delete' => 'delete-form',
      default => $action,
    };
    if (!$entity->hasLinkTemplate($rel)) {
      throw new \Exception("No link template '$rel' found for the '{$entity->getEntityTypeId()}' entity type.");
    }

    $this->visitPath($entity->toUrl($rel)->toString());
  }

  /**
   * Navigates to the revisions form of a node.
   *
   * @param string $title
   *   The title of the node.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  #[When('I visit the revisions page for :title')]
  public function visitRevisionsForm(string $title): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntityByLabel('node', $title);
    $this->visitPath($node->toUrl('version-history')->toString());
  }

  /**
   * Assert we are on the given node path.
   *
   * @param string $title
   *   The title of the node.
   * @param string|null $rel
   *   (optional) The link relationship type (edit-form, delete-form,...).
   *   Defaults to NULL (canonical).
   */
  #[Then('I should be on the :title content page')]
  #[Then('I should be on the :rel page of the :title content')]
  public function assertNodePath(string $title, ?string $rel = NULL): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntityByLabel('node', $title);
    $expected_path = $this->locatePath($node->toUrl($rel)->toString());
    $this->assertSession()->addressEquals($expected_path);
  }

  /**
   * Navigates to the dashboard page of the user.
   */
  #[When('I go to my dashboard')]
  public function visitDashboard(): void {
    $this->visitPath('/dashboard');
  }

  /**
   * Checks if a content entity is published.
   *
   * @param string $bundle
   *   The node bundle.
   * @param string $title
   *   The node title.
   *
   * @throws \Exception
   *   Throws an exception if the content is not published.
   */
  #[Then('the :title :bundle (content )should be published')]
  public function assertNodePublished($bundle, $title): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntityByLabel('node', $title, $bundle);
    if (!$node->isPublished()) {
      throw new \Exception("$title content should be published but it is not.");
    }
  }

  /**
   * Checks if a content entity is published.
   *
   * @param string $bundle
   *   The node bundle.
   * @param string $title
   *   The node title.
   *
   * @throws \Exception
   *   Throws an exception if the content is not published.
   */
  #[Then('the :title :bundle (content )should not be published')]
  public function assertNodeNotPublished($bundle, $title): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntityByLabel('node', $title, $bundle);
    if ($node->isPublished()) {
      throw new \Exception("$title content should not be published but it is.");
    }
  }

  /**
   * Checks if the given content entity has the correct number of revisions.
   *
   * @param string $bundle
   *   The node bundle.
   * @param string $title
   *   The node title.
   * @param int $count
   *   The expected number of revisions.
   */
  #[Then('the :title :bundle (content )should have :count revision(s)')]
  public function assertNodeRevisionCount($bundle, $title, $count): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntityByLabel('node', $title, $bundle);

    $revision_count = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('nid', $node->id())
      ->count()
      ->execute();

    $this->assertSameValues((int) $count, (int) $revision_count);
  }

  /**
   * Replaces human readable labels and values with their real counterparts.
   *
   * This allows us to:
   * - use human readable labels like 'short title' in test scenarios, and map
   *   them to the actual field names like 'field_short_title'.
   * - use human readable values such as a collection name instead of the URL
   *   that is actually used in the OG reference field.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   An object containing the entity properties and fields that are to be used
   *   for creating the node as properties on the object.
   *
   * @throws \Exception
   *   Thrown when a logo could not be uploaded, e.g. because the file could not
   *   be found.
   */
  #[BeforeNodeCreate]
  public function massageFieldsBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $node->type);
    // Replace field label aliases with the actual field names.
    foreach (get_object_vars($node) as $alias => $value) {
      $name = $this->getNodeFieldNameFromAlias($node->type, $alias);
      if ($name !== $alias) {
        unset($node->$alias);
        // Don't set empty values, since we can have multiple aliases that point
        // to the same field (e.g. 'collection' and 'solution' both use the same
        // 'og_audience' field).
        if (!empty($value)) {
          $node->$name = $value;
        }
      }
      // Add the body field format.
      if ($name === 'body' || $name === 'description') {
        $value_key = "{$name}:value";
        $format_key = "{$name}:format";
        $node->{$value_key} = $value;
        $node->{$format_key} = 'content_editor';
        unset($node->{$name});
      }
      if ($name === 'webpage' && filter_var($value, FILTER_VALIDATE_URL)) {
        // \Drupal\Driver\Fields\Drupal8\LinkHandler::expand() expects the
        // value to be an array with at least 2 elements, the first one for the
        // title and the second one for the URI. If only the URI is passed, the
        // string is handled as an array and wrong data are parsed. To avoid
        // this, we are adding a title to the URL. The field parser will split
        // the data into an array and the LinkHandler will handle it correctly.
        // @see \Drupal\Driver\Fields\Drupal8\LinkHandler::expand()
        // @see \Drupal\DrupalExtension\Context\RawDrupalContext::parseEntityFields()
        $node->webpage = "$value - $value";
      }
    }

    if (!empty($node->field_attachment)) {
      // We want to copy it from the fixtures into the file system and register
      // it in the CustomPageContext so it can be cleaned up after the scenario
      // ends. Perform a small dance to get access to the context class from
      // inside this static callback.
      /** @var \Behat\Behat\Context\Environment\InitializedContextEnvironment $environment */
      $environment = $scope->getEnvironment();
      /** @var \Drupal\joinup\Context\CustomPageContext $context */
      $context = $environment->getContext(self::class);
      foreach ($context->explodeDelimitedSeparatedStepArgument($node->field_attachment) as $filename) {
        if (strpos($filename, ':')) {
          preg_match('/target_id:\s*(?<filename>[^ -]*)/', $filename, $matches);
          if (empty($matches['filename'])) {
            throw new \Exception("Could not extract filename from '$filename'");
          }
          $filename = $matches['filename'];
        }
        // The file is tracked by its filename so the value still remains to be
        // the filenames instead of the url.
        $context->createFile($filename);
      }
    }

    // Replace collection and solution group references that use titles with the
    // actual URI. Note that this fails if a URI is supplied for a collection or
    // solution in a Behat test. This is by design, the URIs are not exposed to
    // end users of the site so they should not be used in BDD scenarios.
    if (!empty($node->{OgGroupAudienceHelperInterface::DEFAULT_FIELD})) {
      $entity = $this->getEntityByLabel('node', $node->{OgGroupAudienceHelperInterface::DEFAULT_FIELD});
      $node->{OgGroupAudienceHelperInterface::DEFAULT_FIELD} = $entity->id();
    }

    // Replace the human readable publication statuses with the boolean values
    // that are expected by the Node module.
    self::convertObjectPropertyValues($node, 'status', [
      'unpublished' => 0,
      'published' => 1,
    ]);

    // Replace human readable workflow states with machine names.
    if (!empty($node->field_state)) {
      $node->field_state = self::translateWorkflowStateAlias($node->field_state);
    }
    elseif (!empty($field_definitions['field_state']) && empty($node->field_state) && $node->type != 'contact_information') {
      $node->field_state = 'published';
    }

    if (property_exists($node, 'visit_count')) {
      $node->visit_count = MetaEntity::create([
        'type' => 'visit_count',
        'count' => (int) $node->visit_count,
      ]);
    }

    if (property_exists($node, 'published_at') && !empty($node->published_at)) {
      // If the entry is not numeric, then it means that the scenario is using a
      // human readable version of the timestamp.
      // Example: Sun, 01 Dec 2019 13:00:00 +0100.
      // This will allow dynamic tests as well. `strtotime` will also be able to
      // receive entries like "1 day ago" or "+1 month".
      if (!is_numeric($node->published_at)) {
        // The strtotime() PHP function is using the default timezone when doing
        // the conversion. But we want to stick to UTC in order to avoid
        // timezone and DST issue.
        $node->published_at = date('U', strtotime($node->published_at));
      }
    }

    if (property_exists($node, 'uid') && !empty($node->uid) && !is_numeric($node->uid)) {
      $user = user_load_by_name($node->uid);
      if ($user) {
        $node->uid = $user->id();
      }
    }

    $image_fields = [
      'field_discussion_logo',
      'field_document_logo',
      'field_event_logo',
      'field_news_logo',
      'logo',
    ];
    foreach ($image_fields as $image_field) {
      if (!empty($node->$image_field)) {
        $file = $this->createFile($node->$image_field);
        $node->$image_field = $file->getFileUri();
      }
    }

    if (isset($node->field_paragraphs_body)) {
      $paragraph = Paragraph::create([
        'type' => 'simple_paragraph',
        'field_body' => [
          'value' => $node->field_paragraphs_body,
          'format' => 'content_editor',
        ],
      ]);
      $paragraph->save();
      $node->{"field_paragraphs_body:target_id"} = $paragraph->id();
      $node->{"field_paragraphs_body:target_revision_id"} = $paragraph->getRevisionId();
      unset($node->field_paragraphs_body);
    }

    if (!empty($field_definitions['field_topic']) && empty($node->field_topic)) {
      // For some tests, the node's topic has no relevance. Such tests are
      // allowed to omit an explicit topic. We're assigning a random topic term,
      // to satisfy data integrity and prevent form validation errors.
      $node->field_topic = $this->getRandomTerm('topic', TRUE)->label();
    }

    if (!empty($field_definitions['contact']) && !isset($node->contact)) {
      $node->contact = $this->getDefaultContact()->label();
    }
    if (!empty($field_definitions['owner']) && !isset($node->owner)) {
      $node->owner = $this->getDefaultOwner()->label();
    }
    if (!empty($field_definitions['abstract']) && !isset($node->abstract)) {
      $node->abstract = 'default abstract';
    }

    if (!empty($field_definitions['description']) && empty($node->descritpion)) {
      $node->description = 'default description';
    }
    if (!empty($field_definitions['solution_type']) && empty($node->solution_type)) {
      $node->solution_type = $this->getRandomTerm('solution_type', TRUE)->label();
    }
    if (!empty($field_definitions['keywords']) && empty($node->keywords)) {
      $node->keywords = $this->getRandomTerm('tags')->label();
    }
    if (!empty($field_definitions['classification']) && empty($node->classification)) {
      $node->classification = $this->getRandomTerm('solution_classification')->label();
    }
    if (!empty($field_definitions['domain']) && empty($node->domain)) {
      $node->domain = $this->getRandomTerm('domain')->label();
    }
    if (!empty($field_definitions['language']) && empty($node->language)) {
      $node->language = 'Bulgarian';
    }

    // Handle file URL fields.
    if (!empty($node->resources)) {
      $this->createResourceFileParagraph($node);
    }
  }

  /**
   * Presses button with specified id|name|title|alt|value at a widget.
   *
   * Example: When I press "Sign In" at "Fieldset"
   * Example: And I press "Sign In" at "Custom widget".
   */
  #[When('I press :button at( the) :field( field)')]
  public function pressButtonInWidget($button, $field): void {
    // First check if a fieldset exists containing the given field name. This
    // is used for inline entity forms.
    $element = $this->getSession()->getPage()->find('named', [
      'fieldset',
      $field,
    ]);

    // If this doesn't exist, search for a multivalue entity reference field
    // containing a label for the given field name.
    if (empty($element)) {
      $xpath = '//table[contains(concat(" ", normalize-space(@class), " "), " field-multiple-table ") and //label[text()="' . $field . '"]]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " form-wrapper ")] | //table[contains(concat(" ", normalize-space(@class), " "), " field-multiple-table ") and //h4[contains(concat(" ", normalize-space(@class), " "), " label ") and text()="' . $field . '"]]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " form-wrapper ")]';
      $element = $this->getSession()->getPage()->find('xpath', $xpath);
    }

    $element->pressButton($button);
  }

  /**
   * Fills a date or time field at a datetime widget.
   *
   * Examples:
   * - When I fill in "Start date" with the date "2016-08-29".
   * - When I fill in "Start date" with the date "+2 days".
   * - When I fill in "Start date" with the time "26:59:00".
   * - When I fill in "Start date" with the time "+1 hour".
   *
   * @param string $field_group
   *   The field component's label.
   * @param string $date_component
   *   The field to be filled.
   * @param string $value
   *   The value of the field. This should either be in the format "DD-MM-YYYY"
   *   for dates, "HH:MM:SS" for time, or a relative date format.
   *
   * @throws \Exception
   *    Thrown when more than one elements match the given field in the given
   *    field group.
   */
  #[When('I fill in :field_group with the :date_component :value')]
  public function fillDateField(string $field_group, string $date_component, string $value): void {
    $is_relative_value = $date_component === 'date' ? preg_match('/^\d{2}-\d{2}-\d{4}$/', $value) === 0 : preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 0;

    if ($is_relative_value) {
      $value = date('Y-m-d', strtotime($value));
    }

    $field_selectors = $this->findDateFields($field_group);
    if (count($field_selectors) > 1) {
      throw new \Exception("More than one elements were found.");
    }
    $field_selector = reset($field_selectors);
    $field_selector->fillField(ucfirst($date_component), $value);
  }

  /**
   * Fills the date or time component of a date sub-field in a date range field.
   *
   * @param string $field
   *   The date range field name.
   * @param string $component
   *   The sub-field component. Either "date" or "time".
   * @param string $value
   *   The field value.
   * @param string $date
   *   (optional) The sub-field name. Either "start" or "end". If left empty, it
   *   is assumed that the field is a simple datetime field and not a range,
   *   thus, the date or time components are looked in the whole field.
   *
   * @throws \Exception
   *   Thrown when the date range field is not found.
   */
  #[When('I fill the :date :component of the :field widget with :value')]
  public function fillDateRangeField($field, $component, $value, $date = NULL): void {
    $element = $this->findDateRangeComponent($field, $component, $date);
    $element->setValue($value);
  }

  /**
   * Clears the date or time component of date sub-field in a date range field.
   *
   * @param string $field
   *   The date range field name.
   * @param string $component
   *   The sub-field component. Either "date" or "time".
   * @param string $date
   *   (optional) The sub-field name. Either "start" or "end". If left empty, it
   *   is assumed that the field is a simple datetime field and not a range,
   *   thus, the date or time components are looked in the whole field.
   *
   * @throws \Exception
   *   Thrown when the date range field is not found.
   */
  #[When('I clear the :date :component of the :field widget')]
  #[When('I clear the :component of the :field widget')]
  public function clearDateRangeField($field, $component, $date = NULL): void {
    $element = $this->findDateRangeComponent($field, $component, $date);
    $element->setValue('');
  }

  /**
   * Finds a datetime field.
   *
   * First, get the fields that use a datetime widget. Fields of type 'created'
   * also use the timestamp widget so this method must be able to handle them
   * too. Datetime fields have use a complex widget and render their title as a
   * simple header, not as a label for the field.
   *
   * @param string $field
   *   The field name.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The elements found.
   *
   * @throws \Exception
   *   Thrown when the field was not found.
   */
  public function findDateFields($field): array {
    $field_selectors = $this->getSession()->getPage()->findAll('css', '.field--widget-datetime-timestamp');
    $field_selectors = array_filter($field_selectors, function ($field_selector) use ($field) {
      return $field_selector->has('named', ['content', $field]);
    });
    if (empty($field_selectors)) {
      throw new \Exception("Date field {$field} was not found.");
    }
    return $field_selectors;
  }

  /**
   * Checks, that the given date time field contains the requested text.
   *
   * Example: Then the "date" date field should contain the datetime
   * "31-1-1222 22:22:22".
   *
   * The format of the datetime is "DD-MM-YYYY HH:mm:ii"
   *
   * @param string $field
   *   The field name.
   * @param string $value
   *   The value that is checked to be found.
   *
   * @throws \Exception
   *   Thrown when more than one fields were found or when the field does not
   *   contain the required text.
   */
  #[Then('the :field date field should contain the datetime :value')]
  public function assertDateFieldContains($field, $value): void {
    $elements = $this->findDateFields($field);
    if (count($elements) > 1) {
      throw new \Exception("More than one elements were found.");
    }
    $element = reset($elements);
    $date = $element->findField('Date')->getValue();
    $time = $element->findField('Time')->getValue();
    $this->assertSameValues(trim($date . ' ' . $time), $value);
  }

  /**
   * Checks that the given node has the expected workflow state.
   *
   * @param string $title
   *   The title of the node to check.
   * @param string $type
   *   The node type.
   * @param string $state
   *   The expected state.
   *
   * @throws \Exception
   *   Thrown when the node doesn't exist or doesn't have a state field.
   */
  #[Then('the :title :type content should have the :state state')]
  public function assertNodeWorkflowState(string $title, string $type, string $state): void {
    /** @var \Drupal\joinup_workflow\EntityWorkflowStateInterface $node */
    $node = $this->getEntityByLabel('node', $title, $type);
    $actual = $node->getWorkflowState();
    $this->assertSameValues($state, $actual, "The $title $type content has the expected state '$state' (actual: '$actual')");
  }

  /**
   * Checks the current workflow state on the given entity.
   *
   * @param string $label
   *   The label of the entity.
   * @param string $stateExpected
   *   The expected workflow state's label.
   */
  #[Given('the workflow state of the :label content should be :stateExpected')]
  public function assertEntityWorkflowState(string $label, string $stateExpected): void {
    $entity = $this->getEntityByLabel('node', $label);
    $this->assertCondition(
      $entity instanceof EntityWorkflowStateInterface,
      "The entity doesn't seem to have any workflow."
    );
    // State might be a label or a machine name.
    $stateIdExpected = static::translateWorkflowStateAlias($stateExpected);
    $this->assertSameValues(
      $stateIdExpected,
      $actual = $entity->getWorkflowState(),
      sprintf(
        "Workflow state of '%s' content is different than expected. Expected state is '%s', while the actual one is '%s'.",
        $label,
        $stateIdExpected,
        $actual,
      )
    );
  }

  /**
   * Changes the workflow state of a given entity.
   *
   * @param string $title
   *   The title of the entity to update.
   * @param string $entity_type
   *   The entity type.
   * @param string $state
   *   The workflow state.
   *
   * @throws \Exception
   *   Thrown when the entity doesn't exist, doesn't have a state field, or
   *   cannot be saved.
   */
  #[Given('the workflow state of the :title :entity_type is changed to :state')]
  public function updateWorkflowState(string $title, string $entity_type, string $state): void {
    $entity_type = $this->translateEntityTypeAlias($entity_type);
    /** @var \Drupal\joinup_workflow\EntityWorkflowStateInterface|\Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getEntityByLabel($entity_type, $title);
    $entity->setWorkflowState($state);
    $entity->setNewRevision(TRUE);
    $entity->save();
  }

  /**
   * Checks if the given content belongs to the given parent rdf entity.
   *
   * If there are multiple entities or parents with the same title, then
   * only the first one is checked.
   *
   * @param string $parent
   *   The name of the parent.
   * @param string $parent_bundle
   *   The bundle of the parent.
   * @param string $content_bundle
   *   The bundle of the content.
   * @param string $content
   *   The title of the content.
   */
  #[Then('the :parent :parent_bundle has a :content_bundle titled :content')]
  public function assertContentOgMembership($parent, $parent_bundle, $content_bundle, $content): void {
    $this->assertOgMembership($parent, $parent_bundle, $content, $content_bundle);
  }

  /**
   * Asserts the presence of a tile element with a certain heading.
   *
   * @param string $heading
   *   The heading of the tile.
   *
   * @throws \Exception
   *   Thrown when the expected tile is not found.
   */
  #[Then('I (should )see the :heading tile')]
  public function assertTilePresent(string $heading): void {
    $results = $this->getAllCardsInPage();
    foreach ($results as $result) {
      /** @var \Behat\Mink\Element\Element $result */
      if ($result->getText() === $heading) {
        return;
      }
      // Custom page cards in carousels have a different structure. In order to
      // identify the tile, we need to check the title of the first "a" element.
      // First, retrieve the parent element so that the "a" element is present.
      if ($result->getParent()->find('xpath', "/a[contains(@title, \"{$heading}\")]")) {
        return;
      }
    }

    throw new \Exception("The tile '$heading' was not found on the page");
  }

  /**
   * Count the tiles on the page.
   *
   * @param int $number
   *   The expected number of tiles.
   *
   * @throws \Exception
   *   Thrown when the expected number of tiles is not found.
   */
  #[Then('I (should )see :number tile(s)')]
  public function assertTileCount(int $number): void {
    $results = $this->getAllCardsInPage();
    $nr_found = count($results);
    if ($nr_found != $number) {
      throw new \Exception("Found $nr_found tiles, expected $number");
    }
  }

  /**
   * Asserts that there are no tile elements with a certain heading.
   *
   * @param string $heading
   *   The heading to search.
   *
   * @throws \Exception
   *   Thrown when a tile with the unwanted heading is found.
   */
  #[Then('I (should )not see the :heading tile')]
  public function assertTileNotPresent(string $heading): void {
    $results = $this->getAllCardsInPage();
    foreach ($results as $result) {
      /** @var \Behat\Mink\Element\Element $result */
      if ($result->getText() === $heading) {
        throw new \Exception("The tile '$heading' was found on the page");
      }
    }
  }

  /**
   * Asserts that only the expected tiles are found in the page.
   *
   * @param string $headings
   *   A comma-separated list of tile headings.
   */
  #[Then('the page should show( only) the tiles :headings')]
  public function assertUnorderedTilesPresent(string $headings): void {
    $tiles_in_page = array_keys($this->getTiles());
    $headings = $this->explodeDelimitedSeparatedStepArgument($headings);

    sort($headings);
    sort($tiles_in_page);
    $this->assertSameValues(
      $headings,
      $tiles_in_page,
      sprintf(
        "The found tiles mismatch the expected ones.\nExpected: %s\nActual:%s",
        implode(', ', $headings),
        implode(', ', $tiles_in_page),
      )
    );
  }

  /**
   * Checks that the given tiles are found in the given block.
   *
   * The weight of the tiles are also taken into account.
   *
   * Table format:
   * | Tile title 1 |
   * | Tile title 2 |
   */
  #[Then('I( should) see the( following) tiles in the :region region:')]
  public function assertTilesInBlock(TableNode $titles_table, $region): void {
    $headings_in_page = array_keys($this->getTiles($region));
    $headings_expected = $titles_table->getColumn(0);
    $this->assertSameValues($headings_expected, $headings_in_page, 'The expected tiles were not found in the wanted region or were not in the proper order.');
  }

  /**
   * Assert that the text is visible in a given region.
   */
  #[Then('I see the text :text in the :region region')]
  public function assertTextVisible($text, $region): void {
    $actual = $this->getRegion($region)->getText();
    $actual = preg_replace('/\s+/u', ' ', $actual);
    $regex = '/' . preg_quote($text, '/') . '/ui';
    $message = sprintf('The text "%s" was not found anywhere in the text of the region "%s".', $text, $region);

    if (!(bool) preg_match($regex, $actual)) {
      throw new \Exception($message);
    }
  }

  /**
   * Checks that the given tiles are found in the correct order.
   *
   * Table format:
   * | Tile title 1 |
   * | Tile title 2 |
   *
   * @param \Behat\Gherkin\Node\TableNode $titles_table
   *   The table node containing the headings of tiles.
   * @param string|null $list_title
   *   The title of the list. The assertion searches for tiles in the parent
   *   element of the list heading if given.
   */
  #[Then('I( should) see the( following) tiles in the correct order:')]
  #[Then('in the :list_title list, I should see the following tiles in the correct order:')]
  public function assertOrderedTilesPresent(TableNode $titles_table, ?string $list_title = NULL): void {
    $headings = array_keys($this->getTiles(listTitle: $list_title));
    $headings_expected = array_filter($titles_table->getColumn(0));
    // Allow splitting individual lines by comma.
    $headings_expected = array_map(function ($heading) {
      $split = explode(',', $heading);
      return array_map('trim', $split);
    }, $headings_expected);
    $headings_expected = array_merge(...$headings_expected);
    $this->assertSameValues($headings_expected, $headings, 'The expected tiles were not found or were not in the proper order.');
  }

  /**
   * Checks that the given text strings are found in the correct order.
   *
   * Table format:
   * | Text 1 |
   * | Text 2 |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table node containing the text strings.
   */
  #[Then('I see the texts in the correct order:')]
  public function assertOrderedTextStringsPresent(TableNode $table): void {
    $lines_of_text = $table->getColumn(0);
    $full_text = $this->getSession()->getPage()->getText();
    foreach ($lines_of_text as $text) {
      $pos = strpos($full_text, $text);
      if ($pos === FALSE) {
        throw new \Exception("The text '$text' was not found in the page in the given order.");
      }
      $full_text = substr($full_text, $pos + strlen($text));
    }
  }

  /**
   * Asserts that a certain link is present in a tile.
   *
   * @param string $heading
   *   The heading of the tile.
   * @param string $link
   *   The text of the link.
   *
   * @throws \Exception
   *   Thrown when the tile or the link are not found.
   */
  #[Then('I( should) see the link :text in the :heading tile')]
  public function assertTileContainsLink(string $heading, string $link): void {
    $element = $this->getTileByHeading($heading);
    if (!$element->findLink($link) && !$element->find('xpath', '//a[@aria-label=contains(text(), "' . $link . '")]')) {
      throw new \Exception("The link '$link' was not found in the tile '$heading'.");
    }
  }

  /**
   * Clicks a specific link in a tile.
   *
   * @param string $link
   *   The link.
   * @param string $heading
   *   The heading of the tile.
   *
   * @throws \Exception
   *   Thrown when the expected link is not found in the tile.
   */
  #[Then('I click the :link in the :heading tile')]
  public function iClickLinkInTile(string $link, string $heading): void {
    $element = $this->getTileByHeading($heading);

    $linkElement = $element->findLink($link);
    $linkElementAriaLabel = $element->find('xpath', '//a[@aria-label=contains(text(), "' . $link . '")]');
    if (!$linkElement && !$linkElementAriaLabel) {
      throw new \Exception("The link '$link' was not found in the tile '$heading'.");
    }

    $linkElement ? $linkElement->click() : $linkElementAriaLabel->click();
  }

  /**
   * Asserts that a certain text is present in a tile.
   *
   * @param string $heading
   *   The heading of the tile.
   * @param string $text
   *   The text to search.
   *
   * @throws \Exception
   *   Thrown when the tile is not found or the text is not found in the tile.
   */
  #[Then('I( should) see the text :text in the :heading tile')]
  #[Then('I( should) see the text :text in the :heading card')]
  public function assertTileContainsText(string $heading, string $text): void {
    $element = $this->getTileByHeading($heading);

    if (strpos(trim($element->getText()), $text) === FALSE) {
      throw new \Exception("The text '$text' was not found in the tile '$heading'.");
    }
  }

  /**
   * Asserts that a certain text is not present in a tile.
   *
   * @param string $heading
   *   The heading of the tile.
   * @param string $text
   *   The text that should not be present.
   *
   * @throws \Exception
   *   Thrown when the tile is not found or the text is found in the tile.
   */
  #[Then('I( should) not see the text :text in the :heading tile')]
  #[Then('I( should) not see the text :text in the :heading card')]
  public function assertTileDoesNotContainsText(string $heading, string $text): void {
    $element = $this->getTileByHeading($heading);

    if (strpos(trim($element->getText()), $text) !== FALSE) {
      throw new \Exception("The text '$text' was found in the tile '$heading'.");
    }
  }

  /**
   * Asserts that a certain statistics string is present in a tile.
   *
   * @param string $heading
   *   The heading of the tile.
   * @param string $text
   *   The text to search.
   *
   * @throws \Exception
   *   Thrown when the tile is not found or the text is not found in the tile.
   */
  #[Then('I( should) see the statistics :text in the :heading tile')]
  public function assertTileContainsStatistic(string $heading, string $text): void {
    if (!$this->getTileStatisticElement($heading, $text)) {
      throw new \Exception("The statistics '$text' was not found in the tile '$heading'.");
    }
  }

  /**
   * Gets title statistic element.
   *
   * @param string $heading
   *   The heading of the tile.
   * @param string $text
   *   The text to search.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   Tile statistic element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function getTileStatisticElement(string $heading, string $text): ?NodeElement {
    $element = $this->getTileByHeading($heading);
    $stats = $element->find('css', "[data-joinup-card-stats]");

    return $stats->find('xpath', "//*[contains(text(), $text)]");
  }

  /**
   * Asserts the presence of contextual links in a specific tile.
   *
   * @param string $links
   *   A comma separated list of link texts.
   * @param string $heading
   *   The heading of the tile.
   *
   * @throws \Exception
   *   Thrown when the expected contextual links are not found in the tile.
   */
  #[Then('I (should )see the contextual link(s) :links in the :heading tile')]
  public function assertContextualLinkPresentInTile($links, $heading): void {
    $links = $this->explodeDelimitedSeparatedStepArgument($links);
    $element = $this->getTileByHeading($heading);
    $found_links = array_keys($this->findContextualLinkPaths($element));

    $not_found = array_diff($links, $found_links);
    if (!empty($not_found)) {
      throw new \Exception(sprintf("Contextual links '%s' not found in the tile '%s'", implode(', ', $not_found), $heading));
    }
  }

  /**
   * Asserts the absence of contextual links in a specific tile.
   *
   * @param string $links
   *   A comma separated list of link texts.
   * @param string $heading
   *   The heading of the tile.
   *
   * @throws \Exception
   *   Thrown when the expected contextual links are not found in the tile.
   */
  #[Then('I should not see the contextual link(s) :links in the :heading tile')]
  public function assertContextualLinkNotPresentInTile($links, $heading): void {
    $links = $this->explodeDelimitedSeparatedStepArgument($links);
    $element = $this->getTileByHeading($heading);
    $found_links = array_keys($this->findContextualLinkPaths($element));

    $found = array_intersect($found_links, $links);
    if (!empty($found)) {
      throw new \Exception(sprintf("Unexpected contextual link '%s' found in the tile '%s'", implode(', ', $found), $heading));
    }
  }

  /**
   * Clicks a specific contextual link in a tile.
   *
   * The click is simulated by visiting the URL of the link route.
   *
   * @param string $link
   *   The text of the contextual link.
   * @param string $heading
   *   The heading of the tile.
   *
   * @throws \Exception
   *   Thrown when the expected contextual link is not found in the tile.
   */
  #[Then('I click the contextual link :link in the :heading tile')]
  public function iClickTheContextualLinkInTile($link, $heading): void {
    $element = $this->getTileByHeading($heading);
    $this->clickContextualLink($element, $link);
  }

  /**
   * Asserts that the given form submission buttons are present on the page.
   *
   * @param string $buttons
   *   A comma separated list of button labels.
   * @param int $count
   *   Optional number of buttons that are expected to be present. Use this to
   *   verify that no unexpected additional buttons are present on the page. If
   *   omitted the number of buttons will not be verified.
   *
   * @throws \Exception
   *   Thrown when an expected button is not present or when the number of
   *   buttons is wrong.
   */
  #[Then('(the following )button(s) should be present :buttons')]
  #[Then('the following :count button(s) should be present :buttons')]
  public function assertFormSubmitButtonsVisible($buttons, $count = NULL): void {
    $buttons = $this->explodeDelimitedSeparatedStepArgument($buttons);

    $this->assertSubmitButtonsVisible($buttons, FALSE);

    if (!empty($count)) {
      // Only check the actual form submit buttons, ignore other buttons that
      // might be present in wysiwygs or are used to add multiple values to a
      // field.
      $actual = count($this->getSession()->getPage()->findAll('xpath', '//main//div[contains(concat(" ", normalize-space(@class), " "), " form-actions ")]//input[@type = "submit"]'));
      $this->assertSameValues((int) $count, $actual);
    }
  }

  /**
   * Assert that certain buttons are not present on the page.
   *
   * @param string $buttons
   *   A comma separated list of button labels.
   *
   * @throws \Exception
   *   Thrown when an unexpected button is present.
   */
  #[Then('(the following )buttons should not be present :buttons')]
  public function assertButtonsNotVisible($buttons): void {
    $buttons = $this->explodeDelimitedSeparatedStepArgument($buttons);

    $page = $this->getSession()->getPage();
    $found = [];
    foreach ($buttons as $button) {
      if ($page->findButton($button)) {
        $found[] = $button;
      }
    }

    if (!empty($found)) {
      throw new \Exception('Button(s) should not be present, but were found: ' . implode(', ', $found));
    }
  }

  /**
   * Checks that the plus button menu is shown on the page.
   */
  #[Then('I should see the plus button menu')]
  public function assertPlusButtonMenuPresent(): void {
    $element = $this->getSession()->getPage()->find('css', '#dropdown-add-content');
    $this->assertCondition(!empty($element), 'The plus button menu was not found on the page.');
  }

  /**
   * Checks that the plus button menu is not shown on the page.
   */
  #[Then('I should not see the plus button menu')]
  public function assertPlusButtonMenuNotPresent(): void {
    $element = $this->getSession()->getPage()->find('css', 'div.add-content-menu ul');
    $this->assertCondition(empty($element), 'The plus button menu was found on the page but should not be.');
  }

  /**
   * Click the given link in the plus button menu.
   *
   * On JavaScript enabled browsers this will first click the plus button to
   * open the menu.
   *
   * @param string $link
   *   The link text of the link to click.
   */
  #[When('I click :link in the plus button menu')]
  public function clickLinkInPlusButtonMenu($link): void {
    // Check if we are running in an environment that supports JavaScript like
    // Selenium or PhantomJS. If this is the case the plus button menu will be
    // closed by default and needs to be opened first.
    $this->openPlusButtonMenu();
    $this->getSession()->getPage()->clickLink($link);
  }

  /**
   * Opens the plus button menu on JS-enabled browsers.
   */
  #[When('I open the plus button menu')]
  public function openPlusButtonMenu(): void {
    if (!$this->hasJavascriptSupport()) {
      // Do nothing, if it's a non-Javascript browser.
      return;
    }

    $button = $this->getSession()->getPage()->find('css', '#dropdown-add-content');
    $button->press();
  }

  /**
   * Opens a specific MDL dropdown menu on JS-enabled browsers.
   */
  #[When('I open the header local tasks menu')]
  public function iOpenTheEntityTasksMenu(): void {
    if ($this->hasJavascriptSupport()) {
      $entity_tasks_selector = $this->getRegionSelector('Entity actions');
      $this->getSession()->getPage()->find('css', "$entity_tasks_selector button")->press();
    }
  }

  /**
   * Opens the account menu on JS-enabled browsers.
   */
  #[When('I open the account menu')]
  public function iOpenTheAccountMenu(): void {
    // Non-JS browsers are directly interacting with the HTML, so we consider
    // the account menu to be already open.
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    // Depending on the screen width, a different dropdown item may be visible.
    /** @var \Behat\Mink\Element\NodeElement[] $elements */
    $elements = $this->getSession()->getPage()->findAll('css', '#main-navbar .dropdown');
    foreach ($elements as $element) {
      if ($element->isVisible()) {
        $this->openBootstrapDropdown($element);
        return;
      }
    }
    throw new \RuntimeException('Account menu is not visible and cannot be opened.');
  }

  /**
   * Asserts that the current URL is the same as the parameter.
   *
   * The difference with the steps "Then I should be on" and "Then the url
   * should match" is that the later two strip off the query string and keep
   * only the path to compare.
   *
   * @param string $method
   *   Options: 'be' for strict comparison, 'match' for regexp check.
   * @param string $url
   *   The URL to be checked.
   */
  #[Then('the( current) relative url should :method :url')]
  public function relativeUrlMatches(string $method, string $url): void {
    \assert(in_array($method, ['be', 'match'], TRUE));

    $url = str_replace('\\"', '"', $url);
    $current_url = $this->getSession()->getCurrentUrl();
    $current_url = str_replace($this->getMinkParameter('base_url'), '', $current_url);

    $failed = ($method === 'be' && $current_url !== $url) || ($method === 'match' && !preg_match($url, $current_url));

    if ($failed) {
      throw new ExpectationException("Url $current_url does not match expected $url.", $this->getSession()->getDriver());
    }
  }

  /**
   * Asserts that the plus button menu is empty.
   */
  #[When('the plus button menu should be empty')]
  public function assertEmptyPlusButtonMenu(): void {
    if ($this->hasJavascriptSupport()) {
      if ($this->getSession()->getPage()->findAll('xpath', '//div[contains(concat(" ", normalize-space(@class), " "), " add-content-menu ")]//li[last()]')) {
        throw new \Exception("The plus button menu is not empty.");
      }
    }
  }

  /**
   * Create comment entities.
   *
   * Table format:
   * | message      | author   | parent      | created                   |
   * | Comment body | Jane Dow | Parent node | 2017-08-21T09:08:36+02:00 |
   * | Comment body | John Doe | Parent node |                           |
   *
   * The author and parent fields are mandatory.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The comment data.
   *
   * @throws \Exception
   *   Thrown when the parent node cannot be found, or when the author cannot
   *   be found.
   */
  #[Given('comments:')]
  public function createComments(TableNode $table): void {
    foreach ($table->getColumnsHash() as $comment_values) {
      $query = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('title', $comment_values['parent'])
        ->range(0, 1);
      $result = $query->execute();

      if (empty($result)) {
        throw new \InvalidArgumentException('Unable to load parent of comment.');
      }

      // Reload from database to avoid caching issues and get latest version.
      $id = reset($result);
      $node = Node::load($id);

      // Discussion node-type uses its own field and comment type.
      if ($node->bundle() === 'discussion') {
        $field_name = 'field_replies';
        $comment_type = 'reply';
      }
      else {
        $field_name = 'field_comments';
        $comment_type = 'comment';
      }

      $uid = 0;
      if (!empty($comment_values['author'])) {
        $query = \Drupal::entityQuery('user')->accessCheck(FALSE)
          ->condition('name', $comment_values['author'])
          ->range(0, 1);
        $result = $query->execute();
        $this->assertCondition(!empty($result), 'Unable to load author of comment.');
        $uid = reset($result);
      }

      // Replace field_body alias if present.
      if (isset($comment_values['message'])) {
        $comment_values['field_body'] = $comment_values['message'];
        unset($comment_values['message']);
      }

      unset($comment_values['parent']);
      unset($comment_values['author']);
      $comment_values['uid'] = $uid;
      $values = [
        'comment_type' => $comment_type,
        'status' => CommentInterface::PUBLISHED,
        'uid' => $uid,
        'field_name' => $field_name,
        'entity_type' => 'node',
        'entity_id' => $id,
        'subject' => $this->getRandom()->string(),
      ];
      $comment_values += $values;
      $comment = Comment::create($comment_values);

      // Set creation date if provided.
      if (!empty($comment_values['created'])) {
        // Convert creation date to UNIX time if needed.
        if (!is_int($comment_values['created'])) {
          $comment_values['created'] = strtotime($comment_values['created']);
        }
        $comment->setCreatedTime($comment_values['created']);
      }

      $comment->skip_notification = TRUE;
      $comment->save();
      $this->entities['comment'][$comment->id()] = $comment;
    }
  }

  /**
   * Checks the current workflow state on an entity edit form.
   *
   * @param string $state
   *   The expected workflow state.
   *
   * @throws \Exception
   *   Thrown when the current workflow state field is not shown on the page.
   */
  #[Then('the current workflow state should be :state')]
  public function assertCurrentWorkflowState($state): void {
    $element = $this->getSession()->getPage()->find('css', 'div.current-workflow-state');
    if (empty($element)) {
      throw new \Exception('The current workflow state field is not present on the page.');
    }
    $this->assertSameValues($state, trim($element->getText()));
  }

  /**
   * Checks that the given workflow buttons are visible.
   *
   * @param string $buttons
   *   A comma separated list of workflow button labels.
   */
  #[Then('I should see the workflow buttons :buttons')]
  public function assertWorkflowButtons(string $buttons): void {
    $labels = $this->explodeDelimitedSeparatedStepArgument($buttons);
    $this->assertSubmitButtonsVisible($labels);
  }

  /**
   * Checks that the description of the field with given label is as expected.
   *
   * @param string $label
   *   The label of the field to check.
   * @param string $description
   *   The expected description.
   *
   * @throws \Exception
   *   Thrown when the field does not have a description.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when none of the field's descriptions match the given text.
   */
  #[Then('I should see the description :description for (the ):label( field)')]
  public function assertFieldDescription($label, $description): void {
    $xpath = '//label[text()="' . $label . '"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " form-item ")]//div[contains(concat(" ", normalize-space(@class), " "), " description ")] | //label[text()="' . $label . '"]/ancestor::div[contains(concat(" ", normalize-space(@class), " "), " form-item ")]//small[contains(concat(" ", normalize-space(@class), " "), " description ")]';
    $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);
    if (empty($elements)) {
      throw new \Exception("The $label field does not have a description.");
    }
    foreach ($elements as $element) {
      try {
        $this->assertCondition(str_contains($element->getText(), $description), "The description '$description' was not found in this element.");
        // The description was found, stop searching.
        return;
      }
      catch (ExpectationException $e) {
        // The description did not match the expected string, keep searching.
      }
    }
    throw new ExpectationException("The description $description was not found for the $label field.", $this->getSession()->getDriver());
  }

  /**
   * Asserts that a logo not exists in the header.
   */
  #[Then('I should not see a logo on the header')]
  public function assertNotExistingLogo(): void {
    $xpath = '//div[@data-drupal-group-logo]//img';
    $this->assertSession()->elementsCount('xpath', $xpath, 0);
  }

  /**
   * Asserts that a featured stats exist in the header.
   */
  #[Then('I should see a featured stats on the header')]
  public function assertExistingFeaturedStats(): void {
    $xpath = '//ul[contains(concat(" ", normalize-space(@class), " "), "group-stats")]/@class';
    $this->assertSession()->elementsCount('xpath', $xpath, 1);
  }

  /**
   * Asserts that a featured stats not exist in the header.
   */
  #[Then('I should not see a featured stats on the header')]
  public function assertNotExistingFeaturedStats(): void {
    $xpath = '//ul[contains(concat(" ", normalize-space(@class), " "), "group-stats")]/@class';
    $this->assertSession()->elementsCount('xpath', $xpath, 0);
  }

  /**
   * Programmatically creates node revisions.
   *
   * Table format:
   * | current title            | title            | body            |
   * | <the current node title> | Revisioned title | Revisioned body |
   *
   * @param string $bundle
   *   The bundle of the node.
   * @param \Behat\Gherkin\Node\TableNode $updates_table
   *   The table with the changes to insert in the revision.
   */
  #[Given('(the following ):bundle (content )revisions:')]
  public function createNodeRevisions($bundle, TableNode $updates_table): void {
    foreach ($updates_table->getColumnsHash() as $data) {
      $node = $this->getEntityByLabel('node', $data['current title'], $bundle);
      unset($data['current title']);

      // Crete a fake node object to be able to reuse hooks that help with
      // preparing the field values.
      $fake_node = (object) $data;

      // Retain data which is relied on by the node creation hooks.
      $fake_node->type = $bundle;
      if ($node instanceof GroupContentInterface) {
        $group = $node->getGroup();
        $group_bundle = $group->bundle();
        if (!isset($fake_node->$group_bundle)) {
          $fake_node->$group_bundle = $group->label();
        }
      }

      $this->dispatchHooks(BeforeNodeCreateScope::class, $fake_node);
      // Correction to topic field.
      if (!empty($fake_node->field_topic) && ($topic = $this->getEntityByLabel('taxonomy_term', $fake_node->field_topic, 'topic'))) {
        $fake_node->field_topic = $topic->id();
      }
      $this->parseEntityFields('node', $fake_node);
      // Remove the type property as we cannot change that.
      unset($fake_node->type);

      // The author is handled in \Drupal\Driver\Cores\Drupal8::nodeCreate(),
      // so we have to do it manually.
      if (isset($fake_node->author)) {
        $user = user_load_by_name($fake_node->author);
        if ($user) {
          $node->set('uid', $user->id());
        }
        unset($fake_node->author);
      }

      // Update all fields. Magic setters will take care of everything.
      foreach (get_object_vars($fake_node) as $name => $value) {
        $node->{$name} = $value;
      }

      // Create a new revision.
      $node->setNewRevision();
      // Finally save the node.
      $node->save();
    }
  }

  /**
   * Checks if a field contains a link to a community content page.
   *
   * @param string $field
   *   The name of the field.
   * @param string $title
   *   The title of the community content page.
   */
  #[Then('the :field field should contain the link to the :title page')]
  public function assertFieldContainsLinkToPage($field, $title): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntityByLabel('node', $title);
    $uri = $node->toUrl()->toString();
    $this->assertSession()->fieldValueEquals($field, $uri);
  }

  /**
   * Asserts that a certain link contains the URL of an entity.
   *
   * @param string $link
   *   The link text.
   * @param string $title
   *   The entity title.
   *
   * @throws \Exception
   *   Thrown when the link is not in the page.
   */
  #[Then('/^the share link "(?P<link>[^"]+)" should point to the "(?P<title>[^"]+)" content(?:| entity)$/')]
  public function assertLinkContainsEntityUrl(string $link, string $title): void {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getEntityByLabel('node', $title);
    $link_element = $this->getSession()->getPage()->findLink($link);

    if (!$link_element) {
      throw new \Exception("Link '{$link}' was not found in the page.");
    }

    $entity_url = $entity->toUrl('canonical', [
      'base_url' => rtrim($this->getMinkParameter('base_url') ?? '', '/'),
    ])->setAbsolute()->toString();
    $href = $link_element->getAttribute('href');

    $this->assertCondition(str_contains($href, urlencode($entity_url)), 'The entity URL is not present in the link href.');
  }

  /**
   * Asserts that a certain link points to the given URL.
   *
   * @param string $title
   *   The link title.
   * @param string $link
   *   The link href attribute link.
   *
   * @throws \Exception
   *   Thrown when the link is not in the page or the target href is not exactly
   *   the expected one.
   */
  #[Then('the :title link should point to :link')]
  public function assertLinkPointsToUrl(string $title, string $link): void {
    $link_element = $this->getSession()->getPage()->findLink($title);
    $href = $link_element->getAttribute('href');
    $this->assertSameValues($href, $link);
  }

  /**
   * Assert that a given attribute is not set for a given element in a region.
   */
  #[Then('I( should) not see the :tag element with the :attribute attribute set to :value in the :region( region)')]
  public function assertRegionElementAttribute($tag, $attribute, $value, $region): void {
    $regionObj = $this->getRegion($region);
    $elements = $regionObj->findAll('css', $tag);
    if (empty($elements)) {
      return;
    }
    if (!empty($attribute)) {
      foreach ($elements as $element) {
        $attr = $element->getAttribute($attribute);
        if (!empty($attr)) {
          if (strpos($attr, "$value") !== FALSE) {
            throw new \Exception(sprintf('The "%s" attribute is equal to "%s" on the element "%s" in the "%s" region on the page %s', $attribute, $value, $tag, $region, $this->getSession()->getCurrentUrl()));
          }
        }
      }
    }
  }

  /**
   * Assert that the persistent url link follows a given pattern.
   */
  #[Then('the persistent url should contain :pattern')]
  public function thePersistentUrlShouldContain($pattern): void {
    $this->assertSession()->elementAttributeContains('css', '.permalink', 'href', $pattern);
  }

  /**
   * Simulates picking of an autosuggestion value from a field.
   *
   * @param string $value
   *   The value to select from suggestions.
   * @param string|null $field
   *   The field name.
   *
   * @throws \Exception
   *   Thrown when the field is not found, the field is not an autocomplete
   *   field, when there was a failure retrieving the autocomplete suggestions
   *   or if the wanted suggestion is not found.
   */
  #[When('I pick :value from the :field autocomplete suggestions')]
  #[When('I pick :value from the autocomplete suggestions')]
  public function fillFieldWithNormalisedEntityReference(string $value, ?string $field = NULL): void {
    if ($this->hasJavascriptSupport()) {
      $xpath = '//ul[contains(concat(" ", normalize-space(@class), " "), " ui-autocomplete ")]//a[normalize-space(.) = "' . $value . '"]';
      $this->waitForElementVisible('xpath', $xpath)->click();
    }
    else {
      $element = $this->getSession()->getPage()->findField($field);
      if (!$element) {
        throw new \Exception("The field '$field' not found in the page.");
      }

      if (!$element->hasAttribute('data-autocomplete-path')) {
        throw new \Exception("The field '$field' is not an entity reference autocomplete field.");
      }

      // Use the Mink base uri.
      // @see ::locatePath()
      $client = new Client(['base_uri' => $this->getMinkParameter('base_url')]);
      $uri = $element->getAttribute('data-autocomplete-path') . '?' . UrlHelper::buildQuery([
        'q' => $value,
      ]);
      $response = $client->get($uri);

      if ($response->getStatusCode() !== 200) {
        throw new \Exception("Failed to obtain suggestions for field '$field'.");
      }

      $suggestions = json_decode($response->getBody()->getContents());
      if ($suggestions === NULL) {
        throw new \Exception("Failed to decode suggestions for field '$field'.");
      }

      if (!count($suggestions)) {
        throw new \Exception("No suggestions for value '$value' found in field '$field'.");
      }

      foreach ($suggestions as $suggestion) {
        if ($suggestion->label === $value) {
          $element->setValue($suggestion->value);
          return;
        }
      }

      throw new \Exception("The value '$value' was not found in the autocomplete suggestions.");
    }
  }

  /**
   * Asserts that a certain link in a certain region points to a specific path.
   *
   * Example: Then the link "Login" in the "Header" region
   *          should point to "user/login"
   *
   * @param string $link
   *   The text of the link.
   * @param string $region
   *   The name of the region.
   * @param string $href
   *   The expected path the link should point to, without trailing slashes.
   *
   * @throws \Exception
   *   Thrown when the link is not found.
   */
  #[Then('the link :link in the :region region should point to :href')]
  public function assertRegionLinkHref($link, $region, $href): void {
    $element = $this->getRegion($region)->findLink($link);

    if (!$element) {
      throw new \Exception("The link '$link' was not found in the region $region.");
    }

    $attribute = trim($element->getAttribute('href'), '/');
    $this->assertSameValues($href, $attribute, "The link '$link' in the region '$region' doesn't point to the expected path '$href'.");
  }

  /**
   * Asserts that no links are marked as active in a certain region.
   *
   * @param string $region
   *   The name of the region.
   *
   * @throws \Exception
   *   Thrown when one or more active links are found in the region.
   */
  #[Then('no items should be active in the :region region')]
  public function assertNoActiveLinkInRegion(string $region): void {
    $links = $this->findLinksMarkedAsActive($region);

    if (!empty($links)) {
      $foundLinks = array_map(fn($link) => $link->getText() ?: $link->getHtml(), $links);
      throw new \Exception("Unexpected active links found in the '$region' region: " . implode(', ', $foundLinks));
    }
  }

  /**
   * Asserts that a specific link is marked as active in a certain region.
   *
   * @param string $text
   *   The link text. You can pass multiple links separated by '>' to
   *   indicate more than one active items - can happen in multi-level menus.
   * @param string $region
   *   The name of the region.
   *
   * @throws \Exception
   *   Thrown when multiple or no links at all are found in the region.
   */
  #[Then(':text should be the active item(s) in the :region region')]
  public function assertActiveLinkInRegion($text, $region): void {
    $links = $this->findLinksMarkedAsActive($region);

    if (empty($links)) {
      throw new \Exception("No active links found in the '$region' region.");
    }

    // In order to get the text from an anchor tag that has the is-active class
    // right in it, and not a wrapper, we check both getText() and getHtml().
    $foundLinks = array_map(fn ($link) => $link->getText() ?: $link->getHtml(), $links);
    $expectedLinks = $this->explodeDelimitedSeparatedStepArgument($text, '>');
    sort($foundLinks);
    sort($expectedLinks);

    $this->assertSameValues($expectedLinks, $foundLinks, "The active links in the '$region' region do not match the expected ones.");
  }

  /**
   * Asserts that a tile is marked as featured.
   *
   * @param string $heading
   *   The heading of the tile.
   *
   * @throws \Exception
   *   Thrown when the tile is not marked as featured.
   */
  #[Then('the :tile tile should be marked as featured')]
  public function assertTileMarkedAsFeatured($heading): void {
    $element = $this->getTileByHeading($heading);

    if (!$element->find('css', 'div.star')) {
      throw new \Exception("The tile '$heading' is not marked as featured, but it should be.");
    }
  }

  /**
   * Asserts that a tile is not marked as featured.
   *
   * @param string $heading
   *   The heading of the tile.
   * @param string $type
   *   The tile bundle.
   *
   * @throws \Exception
   *   If the $type is not solution nor community content or the tile is marked
   *   as featured.
   *
   * @todo Due to the bug described in ISAICP-4352, we have to temporary check
   * if a tile is not featured via backend, rather than checking the DOM.
   * Manually testing or running this Behat step definition Behat in test
   * locally doesn't works with DOM check, but for some obscure reasons, that
   * are very hard to be tracked, tests are failing in Continuous PHP bot.
   * Revert back the check that a tile is not featured by checking the DOM, in
   * ISAICP-4849.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4849
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4352
   */
  #[Then('the :tile :type tile should not be marked as featured')]
  public function assertTileNotMarkedAsFeatured(string $heading, string $type): void {
    assert(in_array($type,
      array_merge(CommunityContentHelper::BUNDLES, [
        'solution',
        'collection',
      ])
    ), 'Invalid bundle type.');
    $entity_type_id = $this->translateEntityTypeAlias($type);
    $entity = $this->getEntityByLabel($entity_type_id, $heading);

    if ($entity instanceof PinnableGroupContentInterface && $entity->isPinned()) {
      throw new \Exception("The tile '$heading' is marked as featured, but it shouldn't be.");
    }
  }

  /**
   * Asserts that an tag with the given text exists in the given region.
   *
   * This completes the assertNotRegionElementText of Markup context.
   *
   * @param string $tag
   *   The HTML tag that is being checked.
   * @param string $text
   *   The text that should be present in the HTML tag.
   * @param string $region
   *   The region to which to confine the search for the HTML tag.
   *
   * @throws \Exception
   *   Thrown when the given tag with the given text was not found in the given
   *   region.
   *
   * @see \Drupal\DrupalExtension\Context\MarkupContext::assertNotRegionElementText()
   */
  #[Then('I( should) see a(n) :tag element with the text :text in the :region( region)')]
  public function assertRegionElementText($tag, $text, $region): void {
    $regionObj = $this->getRegion($region);
    $results = $regionObj->findAll('css', $tag);
    if (!empty($results)) {
      foreach ($results as $result) {
        if ($result->getText() === $text) {
          return;
        }
      }
    }
    throw new \Exception(sprintf('The text "%s" was not found in the "%s" element in the "%s" region on the page %s', $text, $tag, $region, $this->getSession()->getCurrentUrl()));
  }

  /**
   * Asserts that a tour is available on the current page.
   */
  #[Then('a tour should be available')]
  public function assertTourAvailable(): void {
    if (!$this->getSession()->getPage()->find('css', 'a.js-tour-start-button')) {
      throw new \Exception("No tour is available on the page.");
    }
  }

  /**
   * Asserts that a tour is not available on the current page.
   */
  #[Then('a tour should not be available')]
  public function assertTourNotAvailable(): void {
    if ($this->getSession()->getPage()->find('css', 'a.js-tour-start-button')) {
      throw new \Exception("Tour is available on the page but should not.");
    }
  }

  /**
   * Press a key in a field.
   *
   * Works only in JavaScript-enabled browsers.
   *
   * @param string $key
   *   The human readable name of the key to press.
   * @param string $field
   *   The field in which to press the key.
   *
   * @throws \Exception
   *   Thrown when the browser doesn't support JavaScript or when the field is
   *   not found.
   */
  #[When('I hit :key( in the keyboard) on the field :field')]
  public function pressKeyInField(string $key, string $field): void {
    if (!$this->hasJavascriptSupport()) {
      throw new \Exception('This step requires JavaScript to run.');
    }

    $element = $this->getSession()->getPage()->findField($field);
    if (!$element) {
      throw new \Exception("Could not find field '$field'");
    }

    $this->pressKeyInElement($key, $element);
  }

  /**
   * Asserts that a tile is marked as pinned.
   *
   * @param string $heading
   *   The heading of the tile.
   */
  #[Then('the :tile tile should be marked as pinned')]
  public function assertTileMarkedAsPinned(string $heading): void {
    if (!$this->getTileByHeading($heading)->find('css', 'div.pin')) {
      throw new ExpectationException("The tile '$heading' is not marked as pinned, but it should be.", $this->getSession()->getDriver());
    }
  }

  /**
   * Asserts that a tile is not marked as pinned.
   *
   * @param string $heading
   *   The heading of the tile.
   */
  #[Then('the :tile tile should not be marked as pinned')]
  public function assertTileNotMarkedAsPinned(string $heading): void {
    if ($this->getTileByHeading($heading)->find('css', 'div.pin')) {
      throw new ExpectationException("The tile '$heading' is marked as pinned, but it shouldn't be.", $this->getSession()->getDriver());
    }
  }

  /**
   * Clears a field (input, textarea, select) value.
   *
   * @param string $field
   *   The field name.
   */
  #[Then('I clear (the content of )the field :field')]
  public function clearField($field): void {
    $this->getSession()->getPage()->fillField($field, '');
  }

  /**
   * Asserts the order and nesting of rows of a draggable menu table.
   *
   * It requires a JavaScript browser to run as many draggable classes are set
   * by JavaScript.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The menu data.
   * @param string $group_label
   *   The group label.
   * @param string $group_bundle
   *   The group bundle.
   */
  #[Then('the draggable menu table of the :group_label :group_type should be:')]
  public function assertDraggableMenuTable(TableNode $table, string $group_label, string $group_bundle): void {
    $this->assertJavaScriptEnabledBrowser();

    $group = $this->getEntityByLabel('node', $group_label, $group_bundle);
    /** @var \Behat\Mink\Element\NodeElement[] $rows */
    $rows = $this->getSession()->getPage()->findAll('css', '#menu-overview tbody tr.draggable');
    $expected = $table->getColumnsHash();
    $this->assertCount(count($expected), $rows);

    foreach ($expected as $delta => $data) {
      $current_row = $rows[$delta];
      $title_element = $current_row->find('css', 'td:first-of-type a:not(.tabledrag-handle)');
      $this->assertSameValues($data['title'], trim($title_element->getText()));

      $menu_link = $this->getMenuLinkByTitle($data['title'], $group->id(), 'navigation');
      $parent_input = $current_row->find('css', 'input[name="links[menu_plugin_id:' . $menu_link->getPluginId() . '][parent]"]');

      // When a parent is available, the line should be indented. Find all
      // indentation elements to assert later on based on the parent value.
      $indentation = $current_row->findAll('css', 'div.indentation');

      if (!isset($data['parent']) || $data['parent'] === '') {
        if (!empty($parent_input)) {
          $this->assertCondition(empty($parent_input->getValue()), "The parent input for menu link '{$data['title']}' should be empty.");
        }
        $this->assertCount(0, $indentation, "The menu link '{$data['title']}' should not be indented but it is.");
      }
      else {
        if (!$parent_input) {
          throw new \Exception('Cannot find parent input field.');
        }

        $parent_link = $this->getMenuLinkByTitle($data['parent'], $group->id(), 'navigation');
        $this->assertSameValues($parent_link->getPluginId(), $parent_input->getValue());
        $this->assertCondition(count($indentation) !== 0, "The menu link '{$data['title']}' should be indented but it's not.");
      }
    }
  }

  /**
   * Checks the order, region, and weight of rows in a draggable table.
   *
   * Example table:
   *   | title  | type                  | weight |
   *   | Type   | Collection content top| 0      |
   *   | Status | Collection content top| 1      |
   */
  #[Then('the search api widget table should be:')]
  public function assertFacetDraggableTable(TableNode $table): void {
    $this->assertJavaScriptEnabledBrowser();

    $rows = $this->getSession()->getPage()->findAll('css', '#facet-display-overview tbody tr.draggable');
    $expected = $table->getColumnsHash();

    $this->assertCount(count($expected), $rows, 'Number of table rows does not match.');

    foreach ($expected as $index => $expected_row) {
      $row = $rows[$index];

      // Check title.
      $title_element = $row->find('css', 'td .tabledrag-cell-content__item');
      $this->assertCondition($title_element !== NULL, "Missing title in row $index.");
      $this->assertSameValues($expected_row['title'], trim($title_element->getText()), "Title mismatch in row $index.");

      // Check type (region).
      $type_select = $row->find('css', 'select.field-region');
      $this->assertCondition($type_select !== NULL, "Missing region (type) select in row $index for '{$expected_row['title']}'.");

      $selected_value = $type_select->getValue();
      $selected_option = $type_select->find('css', 'option[value="' . $selected_value . '"]');
      $this->assertCondition($selected_option !== NULL, 'Selected option not found by value.');
      $option_value = $selected_option->getHtml();
      $this->assertSameValues(
        $expected_row['type'],
        $option_value,
        "Region mismatch in row $index for field '{$expected_row['title']}': expected '{$expected_row['type']}', got '$option_value'."
      );

      // Check weight.
      $weight_select = $row->find('css', 'select.field-weight');
      $this->assertCondition($weight_select !== NULL, "Missing weight select in row $index.");
      $weight_value = $weight_select->getValue();
      $this->assertSameValues(
        $expected_row['weight'],
        $weight_value,
        "Weight mismatch in row $index for field '{$expected_row['title']}': expected '{$expected_row['weight']}', got '$weight_value'."
      );
    }
  }

  /**
   * Drags a table row, specified by title, one position towards a direction.
   *
   * Instead of using the mouse, it uses the keyboard keys so that precise
   * control can be achieved.
   *
   * @param string $title
   *   The title of the menu link that belongs to the row.
   * @param string $direction
   *   The direction to move the row to. One of "up", "down", "left", "right".
   */
  #[Then('I drag the :title table row( to the) :direction')]
  public function dragTitledTableRowTowardsDirection($title, $direction): void {
    $this->assertJavaScriptEnabledBrowser();

    $row = $this->findDraggableTableRowByTitle($title);
    $this->dragTableRowTowardsDirection($row, $direction);
  }

  /**
   * Drags a table row, specified by its position, towards a direction.
   *
   * Instead of using the mouse, it uses the keyboard keys so that precise
   * control can be achieved.
   *
   * @param string $position
   *   The position of the row in the table. First position starts at 1.
   * @param string $direction
   *   The direction to move the row to. One of "up", "down", "left", "right".
   * @param string|null $region
   *   (optional) Only search within a certain region for the given drag icon.
   *   Default to the whole page.
   *
   * @throws \Exception
   *   Thrown when the table row with the given position cannot be found in the
   *   page.
   */
  #[Then('I drag the table row at position :position( to the) :direction')]
  #[Then('I drag the table row in the :region region at position :position( to the) :direction')]
  public function findTableRowByPositionAndDragTowardsDirection(string $position, string $direction, ?string $region = NULL): void {
    $this->assertJavaScriptEnabledBrowser();

    $region = empty($region) ? $this->getSession()->getPage() : $this->getRegion($region);
    $row = $this->findDraggableTableRowByPosition((int) $position, $region);
    $this->dragTableRowTowardsDirection($row, $direction);
  }

  /**
   * Fills the last form field matching the selector with a value.
   *
   * Useful for dynamic parts of a form where a field can be added multiple
   * times, like multiple cardinality fields.
   *
   * @param string $field
   *   The field selector.
   * @param string $value
   *   The field value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown when no field with the given selector are found.
   */
  #[Then('I fill in the latest :field field with :value')]
  public function fillLastFieldWithLabel($field, $value): void {
    $page = $this->getSession()->getPage();
    $fields = $page->findAll('named', ['field', $field]);

    if (empty($fields)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'form field', 'id|name|label|value|placeholder', $field);
    }

    /** @var \Behat\Mink\Element\NodeElement $last */
    $last = array_pop($fields);
    $last->setValue($value);
  }

  /**
   * Selects option in the latest select field with specified named value.
   *
   * @param string $select
   *   Select field.
   * @param string $option
   *   Option name.
   */
  #[When('I select :option from latest :select')]
  public function selectOptionForLatestSelect(string $select, string $option): void {
    $page = $this->getSession()->getPage();

    $selects = $page->findAll('named', ['select', $select]);
    if (empty($selects)) {
      throw new ElementNotFoundException($this->getSession()
        ->getDriver(), 'select', 'label', $select);
    }

    $field = array_pop($selects);
    $field->selectOption($option);
  }

  /**
   * Presses the latest button with a given label.
   *
   * Used for dynamic forms where a button can be added multiple times.
   *
   * @param string $label
   *   The button label.
   */
  #[When('I press latest :label')]
  public function pressLatestButton(string $label): void {
    $page = $this->getSession()->getPage();
    $buttons = $page->findAll('named', ['button', $label]);

    if (empty($buttons)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'button', 'label', $label);
    }

    /** @var \Behat\Mink\Element\NodeElement $last_button */
    $last_button = array_pop($buttons);
    $last_button->press();
  }

  /**
   * Checks if the output is exactly the same as the contents of a given file.
   *
   * @param string $file_path
   *   The path to the file.
   *
   * @throws \Exception
   */
  #[Then('the output should match the file contents of :file')]
  public function theOutputShouldMatchTheFile($file_path): void {
    if ($this->getMinkParameter('files_path')) {
      $fullPath = rtrim(realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_path;
      if (is_file($fullPath)) {
        $file_path = $fullPath;
      }
    }
    $expected_content = trim(file_get_contents($file_path));
    $browser_content = trim($this->getSession()->getPage()->getContent());
    if ($expected_content !== $browser_content) {
      throw new \Exception(sprintf("The output didn't match the fixture. Expected %s, got %s.", $expected_content, $browser_content));
    }
  }

  /**
   * Fills in a link field in the UI.
   *
   * This supports multivalue fields and both the URL and Title fields. The
   * Title field is optional.
   *
   * Table format:
   * | URL                          | Title  |
   * | https://joinup.ec.europa.eu/ | Joinup |
   * | https://www.drupal.org/      | Drupal |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table containing the data to enter in the fields.
   * @param string $label
   *   The label of the link field to fill in.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the table format is invalid.
   * @throws \Exception
   *   Thrown when one of the input fields is not found.
   */
  #[Then('I enter the following for the :label link field:')]
  public function fillLinkField(TableNode $table, string $label): void {
    $rows = array_map(function (array $row): array {
      return array_change_key_case($row);
    }, $table->getColumnsHash());

    $i = 0;
    foreach ($rows as $row) {
      // XPath counts elements starting with 1.
      $i++;

      if (!isset($row['url'])) {
        throw new \InvalidArgumentException('Missing URL column in link table.');
      }
      $xpath = "//input[@id=(//label[text()='$label'])[$i]/@for]";
      $url_input_element = $this->getSession()->getPage()->find('xpath', $xpath);
      if (!$url_input_element) {
        throw new \Exception("URL input field $i not found for link field with label '$label'.'");
      }
      $url_input_element->setValue($row['url']);

      // Title field is optional.
      if (!isset($row['title'])) {
        continue;
      }

      // Retrieve the ID of the title field by taking the ID of the URL field
      // and replacing the `-uri` suffix with `-title`.
      $title_input_id = preg_replace('/uri$/', 'title', $url_input_element->getAttribute('id'), -1, $count);
      if ($count !== 1) {
        throw new \Exception("Malformed HTML ID for URL input field $i for link field with label '$label'.");
      }

      $title_input_element = $this->getSession()->getPage()->findById($title_input_id);
      if (!$title_input_element) {
        throw new \Exception("Title input field $i not found for link field with label '$label'.'");
      }
      $title_input_element->setValue($row['title']);
    }
  }

  /**
   * Asserts the status of the save button in a given region or in the page.
   *
   * @param string $button
   *   The button label.
   * @param string $status
   *   The expected status. Possible values are 'enabled' and 'disabled'.
   * @param string|null $region
   *   (optional) The region name. If left empty, the whole page will be used.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the passed value for the $status variable is not an acceptable
   *   one.
   * @throws \Exception
   *   Thrown when the region or the button are not found or if the expected
   *   status does not match the actual one.
   */
  #[Then('the :button button on the :region region should be :status')]
  #[Then('the :button button should be :status')]
  public function assertButtonStatus(string $button, string $status, ?string $region = NULL): void {
    if (!in_array($status, ['enabled', 'disabled'])) {
      throw new \InvalidArgumentException('Allowed values for status variable are "enabled" and "disabled".');
    }

    if (!empty($region)) {
      $region = $this->getRegion($region);
    }
    else {
      $region = $this->getSession()->getPage();
    }

    $expected_status = $status === 'enabled';
    $button = $this->findNamedElementInRegion($button, 'button', $region);
    $disabled = !($button->getAttribute('disabled') === 'disabled');
    $this->assertSameValues($expected_status, $disabled);
  }

  /**
   * Checks all submission buttons for element.
   */
  #[Then('all submit buttons inside the :selector element should be :state')]
  public function allSubmitButtonsInsideSelectorShouldBeInState($selector, $state): void {
    $page = $this->getSession()->getPage();
    $container = $page->find('css', $selector);

    if (!$container) {
      throw new \Exception("No element found with selector: $selector");
    }

    $inputs = $container->findAll('css', 'input[type="submit"]');

    if (empty($inputs)) {
      throw new \Exception("No submit inputs found inside the element: $selector");
    }

    $shouldBeDisabled = ($state === 'disabled');

    foreach ($inputs as $input) {
      $isDisabled = $input->hasAttribute('disabled');
      $label = $input->getAttribute('value') ?: '(no value)';
      if ($shouldBeDisabled && !$isDisabled) {
        throw new \Exception("Expected submit input '$label' to be disabled, but it was enabled.");
      }
      if (!$shouldBeDisabled && $isDisabled) {
        throw new \Exception("Expected submit input '$label' to be enabled, but it was disabled.");
      }
    }
  }

  /**
   * Checks that there are exactly the given number of elements on the page.
   *
   * @param string $count
   *   The number of elements that should be present on the page.
   * @param string $element_name
   *   The human readable name of the element to find on the page.
   */
  #[Then('there should be exactly :count :element_name on the page')]
  public function assertElementCount(string $count, string $element_name): void {
    $this->assertSameValues((int) $count, count($this->getElementsMatchingElementAlias($element_name)));
  }

  /**
   * Asserts that a paragraph containing the given text is present in the page.
   *
   * @param string $text
   *   The text that should be present in a paragraph.
   */
  #[Then('I should see a paragraph containing the text :text')]
  public function assertParagraphText(string $text): void {
    $element = $this->getSession()->getPage()->find('xpath', '//p[contains(text(), "' . $text . '")]');
    $this->assertCondition(!empty($element), "A paragraph containing the text '$text' was not found in the page.");
  }

  /**
   * Asserts that the content absolute urls exist as links.
   *
   * @param \Behat\Gherkin\Node\TableNode $titles
   *   The titles of the nodes.
   *
   * @throws \Exception
   *   Thrown if any link does not exist in the page.
   */
  #[Given('I should see the absolute urls of the following content entities:')]
  public function assertContentAbsoluteUrlsAsLinkInPage(TableNode $titles): void {
    foreach ($titles->getRows() as $title) {
      $this->assertEntityUrlInSitemap('node', $title[0]);
    }
  }

  /**
   * Asserts that the content absolute urls do not exist as links.
   *
   * @param \Behat\Gherkin\Node\TableNode $titles
   *   The titles of the nodes.
   *
   * @throws \Exception
   *   Thrown if at least a link exists in the page.
   */
  #[Given('I should not see the absolute urls of the following content entities:')]
  public function assertNotContentAbsoluteUrlsAsLinkInPage(TableNode $titles): void {
    foreach ($titles->getRows() as $title) {
      $this->assertEntityUrlInSitemap('node', $title[0], FALSE);
    }
  }

  /**
   * Changes the publication date of an entity.
   *
   * @param string $title
   *   The title of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   * @param string $new_publication_date
   *   The new publication date.
   *
   * @throws \InvalidArgumentException
   *   Thrown when one of the parameters is not correct.
   */
  #[Given('the publication date of the :title :bundle content is changed to :new_value')]
  public function givenPublicationDateChanged(string $title, string $bundle, string $new_publication_date): void {
    $entity = $this->getEntityByLabel('node', $title, $bundle);
    $publication_date = is_numeric($new_publication_date) ? ((int) $new_publication_date) : strtotime($new_publication_date);

    if (empty($new_publication_date)) {
      throw new \InvalidArgumentException("'{$new_publication_date}' could not be converted to a proper timestamp.");
    }

    $entity->set('published_at', $publication_date);
    $entity->save();
  }

  /**
   * Asserts a url in the sitemap response.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_label
   *   The entity label.
   * @param bool $exists
   *   (optional) Whether to assert that a link exists or not exists. Defaults
   *   to TRUE.
   *
   * @throws \Exception
   *   Thrown if the link assertion is not the expected one.
   */
  protected function assertEntityUrlInSitemap(string $entity_type_id, string $entity_label, bool $exists = TRUE): void {
    $entity = $this->getEntityByLabel($entity_type_id, $entity_label);
    $link_text = $entity->toUrl()->setAbsolute()->toString();
    $expected = '<loc>' . $link_text . '</loc>';
    $response = $this->getSession()->getPage()->getContent();
    if ($exists) {
      $this->assertCondition(str_contains($response, $expected), "The URL for '$entity_label' was not found in the sitemap.");
    }
    else {
      $this->assertCondition(!str_contains($response, $expected), "The URL for '$entity_label' should not be in the sitemap but was found.");
    }
  }

  /**
   * Changes the name of a term.
   *
   * @param string $old_name
   *   The current name of the term.
   * @param string $new_name
   *   The new name for the term.
   */
  #[Given('the name of the :old_name term is changed to :new_name')]
  public function givenTermNameChanges(string $old_name, string $new_name): void {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'name' => $old_name,
    ]);
    $term = reset($terms);
    $term->setName($new_name);
    $term->save();
  }

  /**
   * Returns the listing tiles/cards in the page.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   A list of tiles/cards in the page.
   */
  public function getAllCardsInPage(): array {
    $locators = [
      // Card style of Ventuno theme.
      '.tile .tile__title',
      // Carousel for non javascript test.
      '.glide__slides .slide span',
      // Carousel for javascript test.
      '.glide__slides .slide h1',
      // Carousel for custom pages.
      '.glide__slides .slide a',
    ];

    return $this->getSession()->getPage()->findAll('css', implode(', ', $locators));
  }

  /**
   * Closes the modal dialog.
   *
   * @throws \Exception
   *   Thrown if the dialog is not open or the x button is not found.
   */
  #[Given('I close the modal dialog')]
  public function closeMediaModalDialog(): void {
    $xpath = '//div[@class and contains(concat(" ", normalize-space(@class), " "), " ui-dialog ")]//button[@class and contains(concat(" ", normalize-space(@class), " "), " ui-dialog-titlebar-close ")]';
    $button = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($button)) {
      throw new \Exception("Either the dialog is not available or the 'Close' button could not be located.");
    }
    $button->press();
  }

  /**
   * Cleans up the environment after a curated homepage topics scenarios.
   *
   * These scenarios are altering the topic term meta entities. We need to reset
   * them after each run. Because we have a mechanism to delete new content, we
   * are simply resetting the values instead.
   */
  #[AfterScenario('@topic&&@api')]
  public function resetTopicMetaEntity(): void {
    // Remove any entity queue related to homepage topics.
    \Drupal::entityTypeManager()->getStorage('entity_subqueue')
      ->load('homepage_topics')
      ->clearItems()
      ->save();

    $meta_entity_storage = \Drupal::entityTypeManager()->getStorage('meta_entity');
    $ids = $meta_entity_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'topic')
      ->execute();
    if ($ids) {
      foreach ($meta_entity_storage->loadMultiple($ids) as $meta_entity) {
        $meta_entity
          ->set('topics_paragraph', NULL)
          ->set('published', FALSE)
          ->set('image', NULL)
          ->set('description', NULL)
          ->save();
      }
    }
  }

  /**
   * Stores most recent IDs of entity types that should be cleaned up.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   The scenario scope.
   */
  #[BeforeScenario]
  public function beforeScenarioPrepareEntityCleanup(BeforeScenarioScope $scope): void {
    $behat_cleanup_data = \Drupal::state()->get('behat_cleanup_data', []);
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type_id === 'ogmenu_instance') {
        // Skip the ogmenu_instance as it is already handled in og_menu.
        // @see \og_menu_entity_delete
        continue;
      }
      if (isset($behat_cleanup_data[$entity_type_id])) {
        // Keep the original ID so that even broken tests get properly cleaned.
        continue;
      }
      if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }
      $field_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
      $id_key = $entity_type->getKey('id');
      if ($field_definitions[$id_key]->getType() !== 'integer') {
        continue;
      }

      // Get the latest ID of the entity type in the database.
      $ids = \Drupal::entityQuery($entity_type->id())
        ->accessCheck(FALSE)
        ->sort($id_key, 'DESC')
        ->range(0, 1)
        ->execute();

      $behat_cleanup_data[$entity_type_id] = reset($ids) ?: 0;
    }

    // Process menu links last.
    $menu_link_data = $behat_cleanup_data['menu_link_content'] ?? 0;
    unset($behat_cleanup_data['menu_link_content']);
    $behat_cleanup_data['menu_link_content'] = $menu_link_data;
    \Drupal::state()->set('behat_cleanup_data', $behat_cleanup_data);
  }

  /**
   * Cleans up entities created during the scenario.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   The scenario scope.
   */
  #[AfterScenario]
  public function afterScenarioCleanupEntities(AfterScenarioScope $scope): void {
    $behat_cleanup_data = \Drupal::state()->get('behat_cleanup_data', []);
    foreach ($behat_cleanup_data as $entity_type => $id) {
      $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type);

      $id_key = $entity_type->getKey('id');
      // Delete all entities created during the scenario.
      $query = \Drupal::entityQuery($entity_type->id())
        ->accessCheck(FALSE)
        ->condition($id_key, $id, '>');

      $ids = $query->execute();
      if ($ids) {
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type->id());
        $storage->resetCache($ids);
        $entities = $storage->loadMultiple($ids);
        @$storage->delete($entities);
      }
    }

    // Due to the nature of behat running everything in a single request, the
    // cache is not cleared between scenarios. This means that the cache tags
    // are not invalidated and the cache is not cleared. This causes the default
    // icons of paragraphs to be cached when the files are deleted above which
    // breaks tests trying to access paragraphs.
    \Drupal::getContainer()->get('paragraphs_type.uuid_lookup')->clear();

    // Also, clean the media click counter.
    \Drupal::state()->delete('image_library_widget.clicks');
  }

  /**
   * Asserts that the given entity is published or unpublished.
   *
   * @param string $label
   *   The label of the entity.
   * @param string $entityType
   *   The type of the entity (e.g., 'node', 'taxonomy_term').
   * @param string $expectedStatus
   *   The expected publication status of the entity.
   */
  #[Given('/^the publication state of the "([^"]*)" "([^"]*)" should be "([^"]*)"$/')]
  public function theEntityShouldHavePublishedStatus(string $label, string $entityType, string $expectedStatus): void {
    $entity = $this->getEntityByLabel($entityType, $label);
    \assert(in_array($expectedStatus, ['published', 'unpublished']), 'Expected status must be either "published" or "unpublished".');
    $expectedStatus = $expectedStatus === 'published';

    if ($entity->isPublished() !== $expectedStatus) {
      $status = $expectedStatus ? 'published' : 'unpublished';
      throw new \Exception("The entity '$label' is not $status as expected.");
    }
  }

  /**
   * Asserts the number of entities with a given name.
   *
   * @param int $expected
   *   The expected number of entities.
   * @param string $entityType
   *   The type of the entity (e.g., 'node', 'taxonomy_term').
   * @param string $name
   *   The name of the entity to check.
   */
  #[Given('/^there should be (\d+) "([^"]*)" with the name "([^"]*)"$/')]
  public function thereShouldBeWithTheName(int $expected, string $entityType, string $name): void {
    $query = \Drupal::entityQuery($entityType)
      ->accessCheck(FALSE)
      ->condition('name', $name);

    $ids = $query->execute();
    $count = count($ids);

    if ($count !== $expected) {
      throw new \Exception("Expected $expected entities of type '$entityType' with name '$name', but found $count.");
    }
  }

  /**
   * Finds h1 with a span and some text.
   *
   * @param string $title
   *   Title of header link.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  #[Then('I see h1 span element with title :title')]
  public function iSeeH1SpanElement(string $title): void {
    $page = $this->getSession()->getPage();
    $link = $page->find('xpath', '//h1//span[not(ancestor::a)]');

    if (!$link || strpos($link->getText(), $title) === FALSE) {
      throw new ElementNotFoundException(
        $this->getSession(),
        'link',
        'css',
        'h1 span'
      );
    }
  }

}
