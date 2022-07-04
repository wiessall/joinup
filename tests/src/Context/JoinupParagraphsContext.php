<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Exception\WysiwygEditorNotFoundException;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup\Traits\WysiwygTrait;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for testing searches.
 */
class JoinupParagraphsContext extends RawDrupalContext {

  use JavascriptTrait;
  use WysiwygTrait;
  use UtilityTrait;

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
    'Custom content listing' => 'field-content-listing',
  ];

  /**
   * Reusable xpath pattern.
   *
   * @var string
   */
  const PARAGRAPH_ITEM_XPATH = "//div[contains(concat(' ', @class, ' '), ' paragraph ')]";

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
    if ($this->browserSupportsJavaScript()) {
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
    if ($this->browserSupportsJavaScript()) {
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
   * Clicks the remove button in the given paragraphs item.
   *
   * @param string $label
   *   The button label.
   * @param string $field
   *   The field identifier. One of the keys in the
   *   \JoinupParagraphsSubContext::fieldMapping array.
   * @param int|null $delta
   *   (optional) The position of the item to remove. Starts at 1 for
   *   readability. If left empty, the button will be searched in the entire
   *   field.
   *
   * @Given I press :label in the :field field for paragraph :delta
   * @Given I press :label in the :field paragraphs field
   */
  public function clickButtonInParagraphsField(string $label, string $field, ?int $delta = NULL): void {
    if ($delta === NULL) {
      // This is a field button, not linked to any paragraph item, such as the
      // button that adds a new paragraph item.
      $this->getParagraphsElement($field)->findButton($label)->press();
      return;
    }

    $paragraph_item = $this->getParagraphsElementItem($field, --$delta);

    if (!$button = $paragraph_item->findButton($label)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'button', 'label');
    }

    // It might be a button beneath the three dots dropdown.
    if (!$button->isVisible()) {
      $dropdown = $paragraph_item->find('css', 'button.paragraphs-dropdown-toggle');
      $dropdown->click();
    }

    $button->click();
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
    Assert::assertCount($expected_rows, $field->findAll('xpath', "//tr[contains(concat(' ', @class, ' '), 'form-table__row')]"));
  }

  /**
   * Asserts the number of paragraphs in the page.
   *
   * @param int $expected_rows
   *   (optional) The expected number of paragraphs. Defaults to 0.
   *
   * @Then there should be :expected_rows paragraph(s) in the page
   * @Then the page should contain no paragraphs
   */
  public function assertParagraphsCountInPage(int $expected_rows = 0): void {
    Assert::assertCount($expected_rows, $this->getSession()->getPage()->findAll('xpath', static::PARAGRAPH_ITEM_XPATH));
  }

  /**
   * Asserts the exact text content of the paragraphs in the page.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table of expected paragraph texts.
   *
   * @Given I should see the following paragraphs in the given order:
   */
  public function assertExactParagraphsContentInPage(TableNode $table): void {
    $expected = $table->getColumn(0);
    $paragraphs = $this->getSession()->getPage()->findAll('xpath', static::PARAGRAPH_ITEM_XPATH);
    $actual = array_map(function (TraversableElement $paragraph): string {
      return $paragraph->getText();
    }, $paragraphs);
    Assert::assertSame($expected, $actual);
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
      Assert::assertStringContainsString($input->getAttribute('value'), $expected_value);
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

}
