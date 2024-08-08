<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\WysiwygTrait;
use PHPUnit\Framework\Assert;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

/**
 * Behat step definitions for layout paragraphs.
 */
class JoinupLayoutParagraphsContext extends RawDrupalContext {

  use JavascriptTrait;
  use WysiwygTrait;
  use TraversingTrait;

  /**
   * Field mapping. It is the selector without the 'field--name-' prefix.
   *
   * @var array
   */
  const FIELD_MAP = [
    'Custom page body' => 'field-paragraphs-body',
    'Topic meta entity body' => 'topics-paragraph',
  ];

  /**
   * A mapping of layouts to their machine names.
   *
   * @var array
   */
  const LAYOUTS_MAP = [
    'one column' => 'layout_onecol',
    'two columns' => 'layout_twocol_section',
    'three columns' => 'layout_threecol_section',
    'four columns' => 'layout_fourcol_section',
  ];

  /**
   * A mapping of layout properties to their machine names.
   *
   * @var array
   */
  const LAYOUT_PROPERTIES_MAP = [
    'layout' => 'layout',
    'column widths' => 'select',
    'administration label' => 'textfield',
    'background theme' => 'select',
    'extra classes' => 'textfield',
  ];

  /**
   * Reusable xpath pattern.
   *
   * @var string
   */
  const PARAGRAPH_ITEM_XPATH = "//div[contains(concat(' ', @class, ' '), ' layout ')]//div[contains(concat(' ', @class, ' '), ' paragraph ')]";

  /**
   * Adds a layout paragraph in the last position in the given field.
   *
   * @param string $field_alias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param \Behat\Gherkin\Node\TableNode $layout_properties
   *   The layout properties. An example is below.
   * @codingStandardsIgnoreStart
   * | layout            | two columns                                        |
   * | columns           | 50%-50%                                            |
   * | behavior settings | background theme: dark - extra classes: test-class |
   * @codingStandardsIgnoreEnd
   *
   * @When I add a layout paragraph in the :field_alias form field with the following properties:
   */
  public function addLayoutParagraphInFormFieldOnTopLevel(string $field_alias, TableNode $layout_properties): void {
    $this->assertJavaScriptEnabledBrowser();

    $field = $this->getParagraphsElement($field_alias);
    $links = $field->findAll('named', ['link', 'Add layout']);
    end($links)->click();

    // Wait for modal to be opened.
    $this->assertModalToOpen();

    // Get the 'layout' property from the table.
    $layout = $layout_properties->getRowsHash()['layout'];
    assertTrue(isset(self::LAYOUTS_MAP[$layout]), "The layout '$layout' is not defined in the context.");
    $layout = self::LAYOUTS_MAP[$layout];

    $modalContent = $this->getRegion('Modal content');

    // Select the layout.
    $xpath = "//div[contains(concat(' ', @class, ' '), ' layout-select__item ')]/input[@value='$layout']";
    $modalContent->find('xpath', $xpath)->getParent()->click();
    // Wait until AJAX is done.
    $this->getSession()->wait(5000, "jQuery.active === 0");

    // Expand both the "Layout options" and "Settings" fieldsets.
    $modalContent->findButton('Layout Options')->click();
    $modalContent->findButton('Settings')->click();

    // Fill in the available fields.
    foreach ($layout_properties->getRowsHash() as $property => $value) {
      if ($property == 'layout') {
        continue;
      }

      assertTrue(isset(self::LAYOUT_PROPERTIES_MAP[strtolower($property)]), "The layout property '$property' is not defined in the context.");
      $type = self::LAYOUT_PROPERTIES_MAP[strtolower($property)];
      if ($type == 'select') {
        $modalContent->selectFieldOption($property, $value);
      }
      else {
        $modalContent->fillField($property, $value);
      }
    }

    // Finally, click the "Save" button to add the paragraph.
    $modalButtons = $this->getRegion('Modal buttons');
    $modalButtons->findButton('Save')->press();

    // Wait until the button pane is gone.
    $this->assertModalToClose();
  }

  /**
   * Adds a paragraph in the last position in the given field or layout.
   *
   * @param string $paragraph_type
   *   The label of the paragraph type.
   * @param string $field_alias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param \Behat\Gherkin\Node\TableNode $layout_properties
   *   The layout properties. Optionally include a layout position and region.
   *   If a layout position is not provided, the last position is used and the
   *   paragraph will be added without a parent layout paragraph. An example is
   *   below. In the field value, you have to use the format "type: <field type>
   *   - value: <field value>", where <field type> is the field type e.g. text,
   *   select, etc. and <field value> is the value of the field.
   * @codingStandardsIgnoreStart
   * | layout position | 2                                           |
   * | region          | left                                        |
   * | Some text field | type: text - value: This is the field value |
   * @codingStandardsIgnoreEnd
   *
   * @When I add a :paragraph_type paragraph in the :field_alias form field:
   */
  public function addParagraphInFormField(string $paragraph_type, string $field_alias, TableNode $layout_properties): void {
    $this->assertJavaScriptEnabledBrowser();
    // Get the layout position and the region, if available.
    $layout_position = NULL;
    $region = NULL;
    $paragraph_fields = [];
    foreach ($layout_properties->getRowsHash() as $property => $value) {
      if ($property == 'layout position') {
        $layout_position = $value;
      }
      elseif ($property == 'region') {
        $region = $value;
      }
      else {
        $paragraph_fields[$property] = $value;
      }
    }

    $this->getChooseComponentLink($field_alias, $layout_position, $region)->click();

    // An overlay has opened, and we need to select "Layout".
    $xpath = '//div[contains(concat(" ", @class, " "), " lpb-component-list__group--content ")]';
    $this->getSession()->wait(5000, "document.querySelector('div.lpb-component-list__group--content') !== null");
    $layouts_container = $this->getSession()->getPage()->find('xpath', $xpath);
    $layouts_container->clickLink($paragraph_type);
    $this->getSession()->wait(5000, "jQuery.active === 0");

    foreach ($paragraph_fields as $property => $value) {
      // Split the field value into the field type and the field value.
      $field_type = NULL;
      $field_value = NULL;
      preg_match('/type: ([a-z0-9_]+) - value: (.*)/', $value, $matches);
      if (isset($matches[1])) {
        $field_type = $matches[1];
      }
      if (isset($matches[2])) {
        $field_value = $matches[2];
        $field_value = str_replace('\n', "\n", $field_value);
      }

      if ($field_type === 'select') {
        // Wait for the select field to load.
        $this->getSession()->getPage()->selectFieldOption($property, $field_value);
      }
      elseif ($field_type === 'ckeditor') {
        $this->setWysiwygText($property, $field_value);
      }
      else {
        $this->getSession()->getPage()->fillField($property, $field_value);
      }
    }

    // Finally, click the "Save" button to add the paragraph.
    $this->getSession()->getPage()->find('css', '.ui-dialog-buttonpane')->pressButton('Save');
    // Wait until the button pane is gone.
    $this->getSession()->wait(5000, "document.querySelector('.ui-dialog-buttonpane') === null");
  }

  /**
   * Performs an action on a layout paragraph in the given field.
   *
   * @param string $action
   *   The action to perform. Available actions are 'delete', 'edit', 'move up',
   *   'move down', 'duplicate'.
   * @param string $field_alias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param string $position
   *   The position of the layout paragraph to remove.
   *
   * @When I :action from the :field_alias form field the layout paragraph in position :position
   */
  public function actionOnTopLevelLayoutParagraph(string $action, string $field_alias, string $position): void {
    $this->assertJavaScriptEnabledBrowser();
    // The ".is-layout" class is the difference between the actions for layouts
    // and the actions for paragraphs.
    $link = $this->getFormFieldLayoutParagraphs($field_alias)[$position - 1]->find('css', '.lpb-controls.is-layout');

    $this->performAction($link, $action);
  }

  /**
   * Performs an action on the child paragraph in the given layout.
   *
   * @param string $action
   *   The action to perform.
   * @param string $fieldAlias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param string $pPosition
   *   The position of the child paragraph.
   * @param string $lPosition
   *   The position of the layout paragraph.
   *
   * @When I :action from the :fieldAlias field the child :pPosition paragraph of :lPosition layout
   */
  public function actionOnChildLayoutParagraph(string $action, string $fieldAlias, string $pPosition, string $lPosition): void {
    $this->assertJavaScriptEnabledBrowser();
    $layoutParagraph = $this->getFormFieldLayoutParagraphs($fieldAlias)[$lPosition - 1];
    $link = $this->getAllSubParagraphsForLayout($layoutParagraph)[$pPosition - 1]->find('css', '.lpb-controls');

    $this->performAction($link, $action);
  }

  /**
   * Perform an action on link.
   *
   * @param \Behat\Mink\Element\TraversableElement $link
   *   Link element.
   * @param string $action
   *   Action name.
   */
  protected function performAction(TraversableElement $link, string $action): void {
    $actions = ['delete', 'edit', 'move up', 'move down', 'duplicate'];
    assertTrue(in_array($action, $actions), "The action '$action' is not defined in the context. Available actions are ", implode(', ', $actions));

    $link->clickLink(ucfirst($action));
    if ($action === 'delete') {
      $this->getSession()
        ->wait(5000, "document.querySelector('.lpb-dialog') !== null");
      $this->getSession()
        ->getPage()
        ->find('css', '.ui-dialog-buttonpane')
        ->pressButton('Delete');
      $this->getSession()
        ->wait(5000, "document.querySelector('.lpb-dialog') === null");
    }

    if (in_array($action, ['move up', 'move down'])) {
      // Move buttons initiate a specific animation that both move the paragraph
      // and reposition the screen. We need to wait for the animation to be
      // done. 2 seconds is a bit long, but it's the only way to be sure.
      $this->getSession()->wait(2000);
    }

    // Wait until AJAX is done.
    $this->getSession()->wait(5000, "jQuery.active === 0");
  }

  /**
   * Asserts the list of top level layout paragraphs.
   *
   * @param string $field_alias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param \Behat\Gherkin\Node\TableNode $layout_list
   *   The table of layout paragraphs. An example is below.
   *
   * @Given the :field_alias form field should have the following layout paragraphs:
   */
  public function theFormFieldShouldHaveTheFollowingLayoutParagraphs(string $field_alias, TableNode $layout_list): void {
    $layout_paragraphs = $this->getFormFieldLayoutParagraphs($field_alias);
    $actual = [];
    /** @var \Behat\Mink\Element\NodeElement $layout_paragraph */
    foreach ($layout_paragraphs as $layout_paragraph) {
      $actual[] = $layout_paragraph->getAttribute('data-layout');
    }
    $expected = array_map(function (string $layout_alias): string {
      assertTrue(isset(self::LAYOUTS_MAP[$layout_alias]), "The layout '$layout_alias' is not defined in the context.");
      return self::LAYOUTS_MAP[$layout_alias];
    }, $layout_list->getColumn(0));

    assertSame($expected, $actual);
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
   * Asserts the structure of a layout paragraph.
   *
   * @param string $position
   *   The position of the layout paragraph to check.
   * @param string $field_alias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param \Behat\Gherkin\Node\TableNode $structure
   *   The table of layout paragraphs. An example is below.
   * @codingStandardsIgnoreStart
   * | first  | heading |
   * | first  | text    |
   * | second | heading |
   * @codingStandardsIgnoreEnd
   * The first column is the region of the layout paragraph. The second column
   * is the machine name of the paragraph.
   *
   * @When the layout in position :position from the :field_alias form field should have the following structure:
   */
  public function assertLayoutParagraphStructure(string $position, string $field_alias, TableNode $structure): void {
    $layout_paragraphs = $this->getFormFieldLayoutParagraphs($field_alias);
    $layout_paragraph = $layout_paragraphs[$position - 1];
    $actual = [];
    /** @var \Behat\Mink\Element\NodeElement $layout_paragraph */
    foreach ($layout_paragraph->findAll('css', '.layout__region') as $region) {
      $data_region = $region->getAttribute('data-region');
      $actual[$data_region] = [];
      foreach ($region->findAll('css', 'div.paragraph') as $paragraph) {
        $actual[$data_region][] = $paragraph->getAttribute('data-type');
      }
    }

    $expected = [];
    foreach ($structure->getRows() as $row) {
      $expected[$row[0]][] = $row[1];
    }

    assertSame($expected, $actual);
  }

  /**
   * Returns the paragraph field element item.
   *
   * @param string $field
   *   The field designator.
   * @param int $position
   *   Paragraph field position.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The field element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function getParagraphsElementItem(string $field, int $position): TraversableElement {
    assert(isset(self::FIELD_MAP[$field]));

    $element = $this->getParagraphsElement($field);
    $xpath = "(//div[contains(concat(' ', @class, ' '), ' paragraph ')])[$position]";

    $paragraph = $element->find('xpath', $xpath);
    if (empty($paragraph)) {
      throw new \Exception("The $field field does not have an item at row $position.");
    }

    return $paragraph;
  }

  /**
   * Returns the paragraph field element.
   *
   * @param string $field
   *   The field designator.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The field element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When content listing paragraph is not found in the page.
   */
  protected function getParagraphsElement(string $field): TraversableElement {
    assert(isset(self::FIELD_MAP[$field]));
    $mapping = self::FIELD_MAP[$field];

    $xpath = "(//div[contains(concat(' ', @class, ' '), ' field--name-$mapping ')])[1]";
    $element = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($element)) {
      throw new ElementNotFoundException($this->getSession()
        ->getDriver(), 'paragraph field', $field);
    }

    return $element;
  }

  /**
   * Returns a list of layout paragraphs elements.
   *
   * @param string $field_alias
   *   The field designator as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   *
   * @return \Behat\Mink\Element\TraversableElement[]
   *   The layout paragraphs elements.
   */
  protected function getFormFieldLayoutParagraphs(string $field_alias): array {
    $field = $this->getParagraphsElement($field_alias);
    $xpath = '//div[contains(concat(" ", @class, " "), " lpb-layout ") and contains(concat(" ", @class, " "), " paragraph--layout ")]';
    $elements = $field->findAll('xpath', $xpath);
    return $elements ?? [];
  }

  /**
   * Returns a list of paragraphs elements inside provided layout.
   *
   * @param \Behat\Mink\Element\TraversableElement $layout
   *   Layout paragraph.
   *
   * @return \Behat\Mink\Element\TraversableElement[]
   *   The layout paragraphs elements.
   */
  protected function getAllSubParagraphsForLayout(TraversableElement $layout): array {
    $xpath = '//div[contains(concat(" ", @class, " "), " js-lpb-component ") and not (contains(concat(" ", @class, " "), " lpb-layout "))]';
    return $layout->findAll('xpath', $xpath) ?? [];
  }

  /**
   * Returns the add component button.
   *
   * @param string $field_alias
   *   The field designator as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param string|null $parent_layout_position
   *   (optional) The position of the parent layout. Defaults to NULL, which
   *   means the that the button that adds a layout into root will be fetched.
   * @param string|null $parent_layout_region
   *   (optional) The region of the parent layout. If a layout position is
   *   passed, then a region must be passed as well.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The add component button.
   */
  protected function getChooseComponentLink(string $field_alias, ?string $parent_layout_position = NULL, ?string $parent_layout_region = NULL): TraversableElement {
    if ($parent_layout_position === NULL) {
      $field = $this->getParagraphsElement($field_alias);
    }
    else {
      $field = $this->getFormFieldLayoutParagraphs($field_alias)[$parent_layout_position - 1];
      $field = $field->find('css', '.layout__region--' . $parent_layout_region);
    }

    $links = $field->findAll('named', [
      'link',
      'Choose component',
    ]);
    if (empty($links)) {
      throw new \Exception('No choose component links found.');
    }

    // Filter out invisible links (that have the style 'display: none'). This is
    // because, when the layout region has at least one paragraph, then the
    // central button that adds to the region is still in the HTML but hidden.
    // The buttons available at that point, are the ones before and after each
    // paragraph.
    $links = array_filter($links, function (TraversableElement $link) {
      return $link->getAttribute('style') !== 'display: none;';
    });

    return end($links);
  }

}
