<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Exception\WysiwygEditorNotFoundException;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup\Traits\WysiwygTrait;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for testing searches.
 */
class JoinupParagraphsContext extends RawDrupalContext {

  use JavascriptTrait;
  use WysiwygTrait;
  use UtilityTrait;
  use EntityTrait;
  use TestingEntitiesTrait;

  /**
   * A copycat of Behat's internal region mapping.
   *
   * This will be used to determine the selector of each paragraph field name.
   * This is because in some cases, the title of the field is not visible as the
   * field is configured to already display a paragraph form instead of the add
   * buttons.
   *
   * @var array
   */
  const FIELD_MAP = [
    'Custom page body' => 'field-paragraphs-body',
    'Custom content listing' => 'content-listing',
    'Accordion item' => 'field-accordion-item',
  ];

  /**
   * Enters the given text in the given WYSIWYG editor.
   *
   * If this is running on a JavaScript enabled browser it will first click the
   * 'Source' button so the text can be entered as normal HTML.
   *
   * @param string $text
   *   The text to enter in the WYSIWYG editor.
   * @param string $label
   *   The label of the field containing the WYSIWYG editor.
   * @param string $field
   *   The field in which to take action.
   * @param int $position
   *   The delta of the paragraph entry.
   *
   * @When I enter :text in the :label wysiwyg editor in the :field field for paragraph :position
   */
  public function enterTextInWysiwyg(string $text, string $label, string $field, int $position): void {
    $field = $this->getParagraphsElementItem($field, $position);

    // If we are running in a JavaScript enabled browser, first click the
    // 'Source' button so we can enter the text as HTML and get the same result
    // as in a non-JS browser.
    if ($this->hasJavascriptSupport()) {
      $this->pressWysiwygButton($label, 'Source', $field);
      $this->setWysiwygText($label, $text, $field);
    }
    else {
      $field->fillField($label, $text);
    }
  }

  /**
   * Fills in a text field in the given paragraph.
   *
   * Sometimes, when testing complex paragraph structures makes it hard to
   * properly fill in fields due to the subforms and nested elements.
   * Below, all fields are fetched and the one matching the position is filled.
   *
   * @param string $position
   *   The ordinal position of the field.
   * @param string $text
   *   The text to enter.
   * @param string $label
   *   The label of the field.
   * @param string $field
   *   The field in which to take action.
   *
   * @When I fill in the :position :label with :text in the :field field
   */
  public function fillInParagraphsFieldInRawOrder(string $position, string $text, string $label, string $field): void {
    $position = $this->convertOrdinalToNumber($position);
    // Convert ordinal to array index.
    $position--;
    $field = $this->getParagraphsElement($field);
    $fields = $field->findAll('named', ['field', $label]);
    if (empty($fields) || !isset($fields[$position])) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), "Element was not found or not enough elements were found.");
    }

    $field = $fields[$position];
    $field->setValue($text);
  }

  /**
   * Fills in a WYSIWYG field identified by its generic position.
   *
   * Sometimes, when testing complex paragraph structures makes it hard to
   * properly fill in fields due to the subforms and nested elements.
   * Below, all WYSIWYG fields are fetched and the one matching the position is
   * filled.
   *
   * @param string $text
   *   The text to enter in the WYSIWYG editor.
   * @param string $position
   *   The ordinal position of the field.
   * @param string $label
   *   The label of the field containing the WYSIWYG editor.
   * @param string $field
   *   The field in which to take action.
   *
   * @When I enter :text in the :position :label wysiwyg editor in the :field field
   */
  public function enterWysywigInRawOrder(string $text, string $position, string $label, string $field): void {
    $position = $this->convertOrdinalToNumber($position);
    // Convert ordinal to array index.
    $position--;
    $field = $this->getParagraphsElement($field);
    $wysiwygs = $field->findAll('named', ['field', $label]);
    if (empty($wysiwygs) || !isset($wysiwygs[$position])) {
      throw new WysiwygEditorNotFoundException("Wysiwyg editor was not found or not enough editors were found.");
    }

    // Ascend twice so that the label of the field is also included and can be
    // found by xpath search properly.
    $region = $wysiwygs[$position]->getParent()->getParent();
    if ($this->hasJavascriptSupport()) {
      $this->pressWysiwygButton($label, 'Source', $region);
      $this->setWysiwygText($label, $text, $region);
    }
    else {
      $region->fillField($label, $text);
    }
  }

  /**
   * Asserts the existence of a button in a row of paragraphs.
   *
   * @param string $button
   *   The button label.
   * @param string $field
   *   The field name.
   * @param int $delta
   *   The paragraphs row number. Starts at 1 for readability.
   *
   * @Given I should see the :button button in the :field field for paragraph :delta
   */
  public function assertButtonInParagraphsItem(string $button, string $field, int $delta): void {
    $paragraph_item = $this->getParagraphsElementItem($field, --$delta);
    Assert::assertNotEmpty($paragraph_item->findButton($button));
  }

  /**
   * Clicks a link in the given paragraphs item.
   *
   * @param string $label
   *   The link label.
   * @param string $field
   *   The field identifier. One of the keys in the
   *   \JoinupParagraphsSubContext::fieldMapping array.
   * @param int|null $delta
   *   (optional) The position of the item to remove. Starts at 1 for
   *   readability. If left empty, the button will be searched in the entire
   *   field.
   *
   * @Given I click :label in the :field field for paragraph :delta
   * @Given I click :label in the :field paragraphs field
   */
  public function clickLinkInParagraphsField(string $label, string $field, ?int $delta = NULL): void {
    $paragraph_item = ($delta === NULL) ? $this->getParagraphsElement($field) : $this->getParagraphsElementItem($field, --$delta);
    $this->scrollElementIntoView($paragraph_item);
    if (!$link = $paragraph_item->findButton($label)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'button', 'label');
    }

    // It might be a button beneath the three dots dropdown. Check for the
    // driver as well, because only Selenium2Driver supports isVisible().
    if (($this->getSession()->getDriver() instanceof Selenium2Driver) && !$link->isVisible()) {
      // Check first if the button is in the 3-dots dropdown which has the class
      // "paragraphs-dropdown-actions".
      $xpath = "//div[contains(concat(' ', @class, ' '), ' paragraphs-dropdown-actions ')]";
      if ($paragraph_item->find('xpath', $xpath)?->findButton($label)) {
        // Expand the dropdown.
        $dropdown = $paragraph_item->find('css', 'button.paragraphs-dropdown-toggle');
        $dropdown->click();
      }
      // Else, expand the 'add paragraph' dropdown in case the button is one of
      // the 'add paragraph' buttons.
      elseif ($dropdown = $paragraph_item->find('css', 'button.dropdown-toggle')) {
        $dropdown->click();
      }
    }

    $link->click();
  }

  /**
   * Asserts the number of rows in the paragraphs field.
   *
   * @param int $expected_rows
   *   The expected number of rows.
   * @param string $field
   *   The field name.
   *
   * @Given there should be :expected paragraph(s) in the :field field
   */
  public function assertRowsInParagraphsField(int $expected_rows, string $field): void {
    $field = $this->getParagraphsElement($field);
    Assert::assertCount($expected_rows, $field->findAll('xpath', "//tr[contains(concat(' ', @class, ' '), 'draggable')]"));
  }

  /**
   * Asserts the value of a filter from a given content listing paragraph.
   *
   * @param string $paragraph
   *   The "1-based" delta of the content listing paragraph.
   * @param string $filter
   *   The "1-based" delta of the filter within content listing paragraph.
   * @param string|null $expected_value
   *   (optional) The expected value. If omitted, the step definition asserts an
   *   empty value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When content listing paragraph is not found in the page.
   *
   * @Then the :filter filter of the :paragraph content listing paragraph value is :expected_value
   * @Then the :filter filter of the :paragraph content listing paragraph is empty
   */
  public function assertFilterFromParagraphHasValue(string $paragraph, string $filter, ?string $expected_value = NULL): void {
    $paragraph_0_based_delta = $this->convertOrdinalToNumber($paragraph) - 1;
    $field = $this->getParagraphsElement('Custom content listing', $paragraph_0_based_delta);
    $filter_1_based_delta = $this->convertOrdinalToNumber($filter);
    $input = $field->find('xpath', "(//input[@type='text'])[$filter_1_based_delta]");
    if (!$input) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), "Text field from $paragraph paragraph");
    }
    if ($expected_value) {
      Assert::assertStringContainsString($expected_value, $input->getAttribute('value'));
    }
    else {
      Assert::assertEmpty($input->getAttribute('value'));
    }
  }

  /**
   * Presses button of a filter from a given content listing paragraph.
   *
   * @param string $button
   *   The expected button name.
   * @param string $paragraph
   *   The "1-based" delta of the content listing paragraph.
   * @param string $filter
   *   The "1-based" delta of the filter within content listing paragraph.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When content listing paragraph is not found in the page.
   *
   * @Then I click :button at :filter filter of the :paragraph content listing paragraph
   */
  public function pressRemoveFilterFromParagraph(string $button, string $paragraph, string $filter): void {
    $paragraph_0_based_delta = $this->convertOrdinalToNumber($paragraph) - 1;
    $field = $this->getParagraphsElement('Custom content listing', $paragraph_0_based_delta);
    $filter_1_based_delta = $this->convertOrdinalToNumber($filter);
    $input = $field->find('xpath', "(//input[@type='submit' and @value='$button'])[$filter_1_based_delta]");
    if (!$input) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), "Button contained $filter filter from $paragraph paragraph");
    }
    $this->scrollElementIntoView($input);
    $input->click();
  }

  /**
   * Returns the paragraph field element.
   *
   * @param string $field
   *   The field designator.
   * @param int $position
   *   (optional) The zero-based paragraph field position. If empty, the first
   *   paragraph will be returned. Defaults to 0.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The field element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When content listing paragraph is not found in the page.
   */
  protected function getParagraphsElement(string $field, int $position = 0): TraversableElement {
    assert(isset(self::FIELD_MAP[$field]));
    $mapping = self::FIELD_MAP[$field];

    // XPath element delta is "1-based".
    $position++;

    $xpath = "(//div[contains(concat(' ', @class, ' '), ' field--name-$mapping ')])[$position]";
    $element = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($element)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'paragraph field', $field);
    }
    return $element;
  }

  /**
   * Returns the paragraph field element item.
   *
   * @param string $field
   *   The field designator.
   * @param int $delta
   *   The zero-based delta of the paragraph field item.
   * @param int $position
   *   (optional) The zero-based paragraph field position. If empty, the first
   *   paragraph will be returned. Defaults to 0.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The field element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the paragraph field cannot be found.
   */
  protected function getParagraphsElementItem(string $field, int $delta, int $position = 0): TraversableElement {
    assert(isset(self::FIELD_MAP[$field]));
    $mapping = self::FIELD_MAP[$field];

    $element = $this->getParagraphsElement($field, $position);

    // Normally, the ID of the element we are seeking is in the form of
    // '{field-name}-{delta}-item-wrapper'. However, when submitting the remove
    // button for example, it receives a small hash for a suffix and receives
    // the form of '{field-name}-{delta}-item-wrapper--{hash}'.
    $xpath = "//div[starts-with(@id, '$mapping-$delta-item-wrapper')]";
    $elements = $element->findAll('xpath', $xpath);
    if (empty($elements)) {
      throw new \Exception("The $field field does not have an item at row $delta.");
    }
    if (count($elements) > 1) {
      throw new \Exception("Multiple instances of the $field field were found in the page at row $delta.");
    }
    return reset($elements);
  }

  /**
   * Cleans up the paragraphs entities created when nodes where created.
   *
   * This uses the orphan paragraphs purger queue worker.
   *
   * @AfterScenario
   */
  public function paragraphsCleanup(): void {
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $worker */
    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('entity_reference_revisions_orphan_purger');
    $queue = \Drupal::queue('entity_reference_revisions_orphan_purger');

    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

  /**
   * Asserts that there are results in the carousel paragraph.
   *
   * @param string $heading
   *   The heading of the carousel paragraph.
   *
   * @Then I should see results in the :heading carousel
   */
  public function iShouldResultsInTheCarousel(string $heading): void {
    $xpath = "//div[contains(concat(' ', @class, ' '), ' paragraph--carousel ')]";
    $xpath .= "//h2[contains(concat(' ', @class, ' '), ' paragraph__heading ')]";
    $xpath .= "[contains(text(), '$heading')]";
    $element = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($element)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'paragraph', 'paragraph--carousel', $heading);
    }

    $xpath = "//article[contains(concat(' ', @class, ' '), ' glide__slide ')]";
    $elements = $element->getParent()->findAll('xpath', $xpath);
    if (empty($elements)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'paragraph', 'glide__slide', $heading);
    }
  }

  /**
   * Appends paragraph to entity.
   *
   * @param string $paragraph_type
   *   Paragraph type.
   * @param string $parent_field
   *   Parent field name.
   * @param string $parent_type_id
   *   Parent type id.
   * @param string $parent_name
   *   Parent name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Paragraph's fields.
   *
   * @Given I append :paragraph_type to :parent_field field in :parent_type_id entity with following :parent_name title:
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function iAppendParagraphToFieldInEntityWithFollowingTitle(
    string $paragraph_type,
    string $parent_field,
    string $parent_type_id,
    string $parent_name,
    TableNode $table,
  ): void {
    $entity = $this->getEntityByLabel($parent_type_id, $parent_name);
    $paragraph = $this->createParagraph($paragraph_type, $table);
    $this->appendParagraphToEntity($entity, $paragraph, $parent_field);
  }

  /**
   * Appends a poll paragraph.
   *
   * @param string $poll
   *   Poll name.
   * @param string $parent_field
   *   Parent field name.
   * @param string $parent_type_id
   *   Parent type id.
   * @param string $parent_name
   *   Parent name.
   *
   * @Given I append poll :poll to :parent_field field in :parent_type_id entity with following :parent_name title
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function iAppendPollToFieldInEntityWithFollowingTitle(
    string $poll,
    string $parent_field,
    string $parent_type_id,
    string $parent_name,
  ): void {
    $poll = $this->getEntityByLabel('poll', $poll);
    $parentEntity = $this->getEntityByLabel($parent_type_id, $parent_name);

    $paragraph = $this->createParagraph(
      'poll',
      ['field_paragraphs_poll' => [$poll->id()]]
    );
    $this->appendParagraphToEntity($parentEntity, $paragraph, $parent_field);
  }

  /**
   * Appends a content listing paragraph.
   *
   * @param string $parent_field
   *   Parent field name.
   * @param string $parent_type_id
   *   Parent type id.
   * @param string $parent_name
   *   Parent name.
   * @param \Behat\Gherkin\Node\PyStringNode $value
   *   Serialized value.
   *
   * @Given I append content listing to :parent_field field in :parent_type_id entity with following :parent_name title with:
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function iAppendContentListingToFieldInEntityWithFollowingTitleWith(
    string $parent_field,
    string $parent_type_id,
    string $parent_name,
    PyStringNode $value,
  ): void {
    $entity = $this->getEntityByLabel($parent_type_id, $parent_name);

    $paragraph = $this->createParagraph(
      'content_listing',
      ['content_listing:value' => $value->getRaw()]
    );
    $this->appendParagraphToEntity($entity, $paragraph, $parent_field);
  }

  /**
   * Helper method to transform table data into the correct field array.
   *
   * @param string $field
   *   Field name.
   * @param mixed $value
   *   Field value.
   *
   * @return array
   *   Transformed data.
   */
  protected function transformParagraphField(string $field, mixed $value): array {
    $data = $this->transformParagraphValue($field, $value);

    $keys = explode(':', $field);
    foreach (array_reverse($keys) as $key) {
      $data = [$key => $data];
    }

    return $data;
  }

  /**
   * Helper method to transform table field values.
   *
   * @param string $field
   *   Field name.
   * @param mixed $value
   *   Field value.
   *
   * @return array
   *   Transformed value.
   */
  protected function transformParagraphValue(string $field, mixed $value): mixed {
    return match ($field) {
      'content_listing:value' => unserialize($value, ['allowed_classes' => FALSE]),
      default => $value,
    };
  }

  /**
   * Append paragraph to entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   * @param string $field_name
   *   Field name.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function appendParagraphToEntity(ContentEntityInterface $entity, ParagraphInterface $paragraph, string $field_name): void {
    if (!$entity->hasField($field_name)) {
      throw new \Exception(sprintf("Field not found: %s", $field_name));
    }

    $entity->get($field_name)->appendItem(
      [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ]
    );
    $entity->save();
  }

  /**
   * Creates a paragraph with the given values.
   *
   * If saved, then the paragraph is queued to be removed after the current test
   * is finished.
   *
   * @param string $type
   *   The type of the paragraph.
   * @param \Behat\Gherkin\Node\TableNode|array $table
   *   The data to add in the paragraph.
   * @param bool $save
   *   Whether the paragraph should be saved or not. Optional, defaults to TRUE.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The paragraph instance.
   */
  protected function createParagraph(
    string $type,
    TableNode|array $table,
    bool $save = TRUE,
  ): ParagraphInterface {
    $rawArray = $table instanceof TableNode ? $table->getRowsHash() : $table;
    $rawArray['type'] = $type;
    foreach ($rawArray as $field => $value) {
      $rawArray = array_replace_recursive($rawArray, $this->transformParagraphField($field, $value));
    }

    $paragraph = Paragraph::create($rawArray);
    if ($save) {
      $paragraph->save();
      $this->entities['paragraph'][$paragraph->id()] = $paragraph;
    }
    return $paragraph;
  }

  /**
   * Appends a paragraph to the current entity form's field.
   *
   * @param string $type
   *   Paragraph entity.
   * @param string $fieldName
   *   Field name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Paragraph values.
   * @param string $layoutId
   *   The ID of the layout paragraph type to add the paragraph into. Optional,
   *   Defaults to the one-column paragraph with ID "layout_onecol".
   * @param string $regionId
   *   The field name in the layout paragraph to add the paragraph into.
   *   Optional, defaults to "content".
   *
   * @Given I append :type to :fieldName layout paragraphs field
   * @Given I append :type to :fieldName layout paragraphs field with :layoutId layout
   * @Given I append :type to :fieldName layout paragraphs field into :layoutId layout's :regionId region
   */
  public function appendParagraphToCurrentForm(
    string $type,
    string $fieldName,
    TableNode $table,
    string $layoutId = 'layout_onecol',
    string $regionId = 'content',
  ): void {
    // Layout Paragraphs uses private temporary store to store the whole
    // paragraph structure, including the actual entities as well. Since the
    // tests' PHP thread is independent (it iteracts with Drupal through the
    // actual driver), we need to switch to the user account which is actually
    // logged into the Drupal instance to get the corresponding layout
    // paragraphs layout object.
    $lpStorageKeyInput = $this->assertSession()->elementExists('xpath', "//input[@name='{$fieldName}[layout_paragraphs_storage_key]']");
    $drupalSettingsElement = $this->assertSession()->elementExists('xpath', '//script[@data-drupal-selector="drupal-settings-json"]');
    $drupalSettingsRaw = $drupalSettingsElement->getText() ?: $drupalSettingsElement->getHtml();
    $uid = Json::decode($drupalSettingsRaw)['user']['uid'] ?? 0;
    $storageKey = $lpStorageKeyInput->getValue();
    $accountSwitcher = \Drupal::service('account_switcher');
    assert($accountSwitcher instanceof AccountSwitcherInterface);
    $accountSwitcher->switchTo(User::load($uid));
    Assert::assertArrayHasKey(
      $type,
     \Drupal::entityTypeManager()->getStorage('paragraphs_type')->loadMultiple(),
      sprintf(
        "The specified paragraphs type '%s' is not available",
        $type
      )
    );
    try {
      $lpTempStore = \Drupal::service('layout_paragraphs.tempstore_repository');
      assert($lpTempStore instanceof LayoutParagraphsLayoutTempstoreRepository);
      $layoutDiscovery = \Drupal::service('plugin.manager.core.layout');
      assert($layoutDiscovery instanceof LayoutPluginManagerInterface);
      $layoutParagraphsLayout = $lpTempStore->getWithStorageKey($storageKey);
      // Fail loud if the layout object cannot be loaded...
      Assert::assertInstanceOf(LayoutParagraphsLayout::class, $layoutParagraphsLayout);
      // ...And also when the layout is missing.
      Assert::assertTrue($layoutDiscovery->hasDefinition($layoutId));
      $layoutInstance = $layoutDiscovery->createInstance($layoutId);
      $itemParagraph = $this->createParagraph($type, $table, FALSE);
      // Create the layout paragraph and set its settings.
      $layoutParagraph = $this->createParagraph('layout', [], FALSE);
      $layoutParagraph->setBehaviorSettings(
        'layout_paragraphs',
        [
          'layout' => $layoutId,
          'config' => $layoutInstance->getConfiguration(),
          'parent_uuid' => NULL,
          'region' => NULL,
        ]
      );
      Assert::assertContainsEquals($regionId, $layoutInstance->getPluginDefinition()->getRegionNames());
      $layoutParagraphsLayout->appendComponent($layoutParagraph);
      $layoutParagraphsLayout->insertIntoRegion($layoutParagraph->uuid(), $regionId, $itemParagraph);
      $lpTempStore->set($layoutParagraphsLayout);
    }
    finally {
      $accountSwitcher->switchBack();
    }
  }

}
