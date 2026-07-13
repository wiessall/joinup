<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeStep;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\WysiwygTrait;

/**
 * Behat step definitions for layout paragraphs.
 */
class JoinupLayoutParagraphsContext extends RawDrupalContext {

  use AssertTrait;
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
    'Topic term body' => 'topics-paragraph',
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
    'column widths' => 'select',
    'administration label' => 'textfield',
    'background theme' => 'select',
    'extra classes' => 'textfield',
  ];

  /**
   * The buttons of the builder UI, as xpath relative to their container.
   *
   * The controls bar is prepended to the component it belongs to, and the
   * insert buttons are prepended and appended to the component, or the region,
   * they add into. The buttons are matched by class rather than by label
   * because the labels are configurable.
   *
   * @var array
   *
   * @see \Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder::buildComponent()
   * @see \Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder::addJsUiElement()
   * @see layout_paragraphs.settings.yml
   */
  const BUTTONS = [
    'edit' => self::CONTROLS_XPATH . '//*[contains(concat(" ", @class, " "), " lpb-edit ")]',
    'delete' => self::CONTROLS_XPATH . '//*[contains(concat(" ", @class, " "), " lpb-delete ")]',
    'duplicate' => self::CONTROLS_XPATH . '//*[contains(concat(" ", @class, " "), " lpb-duplicate ")]',
    'move up' => self::CONTROLS_XPATH . '//*[contains(concat(" ", @class, " "), " lpb-up ")]',
    'move down' => self::CONTROLS_XPATH . '//*[contains(concat(" ", @class, " "), " lpb-down ")]',
    // The buttons that add a sibling before or after a given component.
    'add before' => './a[contains(concat(" ", @class, " "), " lpb-btn--add ") and contains(concat(" ", @class, " "), " before ")]',
    'add after' => './a[contains(concat(" ", @class, " "), " lpb-btn--add ") and contains(concat(" ", @class, " "), " after ")]',
    // The centered button of an empty container: a region, or the builder root.
    'add component' => './/a[contains(concat(" ", @class, " "), " lpb-btn--add ") and contains(concat(" ", @class, " "), " center ")]',
  ];

  /**
   * The controls bar, a direct child of the component it belongs to.
   *
   * @var string
   */
  const CONTROLS_XPATH = './div[contains(concat(" ", @class, " "), " lpb-controls ")]';

  /**
   * The components of the builder root, i.e. the sections.
   *
   * @var string
   */
  const SECTION_XPATH = './/div[contains(concat(" ", @class, " "), " js-lpb-component ") and not(ancestor::div[contains(concat(" ", @class, " "), " js-lpb-component ")])]';

  /**
   * The components held by a region.
   *
   * Layouts cannot be nested, so every component in a region is a leaf.
   *
   * @var string
   */
  const REGION_COMPONENT_XPATH = './/div[contains(concat(" ", @class, " "), " js-lpb-component ")]';

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
   */
  #[When('I add a layout paragraph in the :field_alias form field with the following properties:')]
  public function addLayoutParagraphInFormFieldOnTopLevel(string $field_alias, TableNode $layout_properties): void {
    $this->assertJavaScriptEnabledBrowser();

    // Appending a section to the builder root opens the same modal as the
    // centered button of an empty builder does.
    $builder = $this->getBuilder($field_alias);
    $this->clickAddButtonAtEndOf($builder, self::SECTION_XPATH, NULL);

    // Get the 'layout' property from the table.
    $layout = $layout_properties->getRowsHash()['layout'];
    $this->assertCondition(isset(self::LAYOUTS_MAP[$layout]), "The layout '$layout' is not defined in the context.");
    $layout = self::LAYOUTS_MAP[$layout];

    $modalContent = $this->getRegion('Modal content');

    // Select the layout.
    $xpath = "//div[contains(concat(' ', @class, ' '), ' layout-select__item ')]/input[@value='$layout']";
    $modalContent->find('xpath', $xpath)->getParent()->click();
    $this->waitForAjax();

    // Expand both the "Layout options" and "Settings" fieldsets.
    $modalContent->findButton('Layout Options')->click();
    $modalContent->findButton('Settings')->click();

    // Fill in the available fields.
    foreach ($layout_properties->getRowsHash() as $property => $value) {
      if ($property === 'layout') {
        continue;
      }

      $this->assertCondition(isset(self::LAYOUT_PROPERTIES_MAP[strtolower($property)]), "The layout property '$property' is not defined in the context.");
      $type = self::LAYOUT_PROPERTIES_MAP[strtolower($property)];
      if ($type === 'select') {
        $modalContent->selectFieldOption($property, $value);
      }
      else {
        $modalContent->fillField($property, $value);
      }
    }

    // Finally, click the "Save" button to add the paragraph.
    $save = $this->waitForElementVisible('css', '.ui-dialog-buttonpane .lpb-btn--save');
    $save->press();

    // Wait until the button pane is gone.
    $this->waitForModalToClose();
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
   *   If a layout position is not provided, the last region of the builder is
   *   used. An example is below. In the field value, you have to use the format
   *   "type: <field type> - value: <field value>", where <field type> is the
   *   field type e.g. text, select, etc. and <field value> is the value of the
   *   field.
   * @codingStandardsIgnoreStart
   * | layout position | 2                                           |
   * | region          | left                                        |
   * | Some text field | type: text - value: This is the field value |
   * @codingStandardsIgnoreEnd
   */
  #[When('I add a :paragraph_type paragraph in the :field_alias form field:')]
  public function addParagraphInFormField(string $paragraph_type, string $field_alias, TableNode $layout_properties): void {
    $this->assertJavaScriptEnabledBrowser();
    // Get the layout position and the region, if available.
    $layout_position = NULL;
    $region = NULL;
    $paragraph_fields = [];
    foreach ($layout_properties->getRowsHash() as $property => $value) {
      if ($property === 'layout position') {
        $layout_position = $value;
      }
      elseif ($property === 'region') {
        $region = $value;
      }
      else {
        $paragraph_fields[$property] = $value;
      }
    }

    // Append the component to the region, which opens the modal.
    $region_element = $this->getRegionToAddInto($field_alias, $layout_position, $region);
    $this->clickAddButtonAtEndOf($region_element, self::REGION_COMPONENT_XPATH, $this->getOwnerComponent($region_element));

    // Select the paragraph type.
    $ui_dialog = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $ui_dialog->find('named', ['link', $paragraph_type])->click();
    $this->waitForAjax();

    // Fill fields.
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
    $this->waitForModalToClose();
  }

  /**
   * Clicks a button of a section.
   *
   * @param string $button
   *   The button to click, as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::BUTTONS.
   * @param string $position
   *   The position of the section in the builder.
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP. Defaults
   *   to NULL, which uses the only builder in the page.
   */
  #[When('I click the :button button in section :position')]
  #[When('I click the :button button in section :position of the :field_alias form field')]
  public function clickButtonInSection(string $button, string $position, ?string $field_alias = NULL): void {
    $this->assertJavaScriptEnabledBrowser();
    $section = $this->getSection($position, $field_alias);

    $this->clickBuilderButton($button, $section, $section);
  }

  /**
   * Clicks a button of a paragraph inside a section.
   *
   * @param string $button
   *   The button to click, as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::BUTTONS.
   * @param string $paragraph_position
   *   The position of the paragraph inside the section.
   * @param string $position
   *   The position of the section in the builder.
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP. Defaults
   *   to NULL, which uses the only builder in the page.
   */
  #[When('I click the :button button of paragraph :paragraph_position in section :position')]
  #[When('I click the :button button of paragraph :paragraph_position in section :position of the :field_alias form field')]
  public function clickButtonInParagraph(string $button, string $paragraph_position, string $position, ?string $field_alias = NULL): void {
    $this->assertJavaScriptEnabledBrowser();
    $section = $this->getSection($position, $field_alias);
    $paragraphs = $this->getAllSubParagraphsForLayout($section);
    $this->assertCondition(isset($paragraphs[$paragraph_position - 1]), "There is no paragraph in position $paragraph_position in section $position.");

    $this->clickBuilderButton($button, $paragraphs[$paragraph_position - 1], $paragraphs[$paragraph_position - 1]);
  }

  /**
   * Clicks a button of a region of a section.
   *
   * @param string $button
   *   The button to click, as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::BUTTONS.
   * @param string $region
   *   The region of the section, e.g. 'first'.
   * @param string $position
   *   The position of the section in the builder.
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP. Defaults
   *   to NULL, which uses the only builder in the page.
   */
  #[When('I click the :button button in the :region region of section :position')]
  #[When('I click the :button button in the :region region of section :position of the :field_alias form field')]
  public function clickButtonInRegion(string $button, string $region, string $position, ?string $field_alias = NULL): void {
    $this->assertJavaScriptEnabledBrowser();
    $section = $this->getSection($position, $field_alias);

    // The region belongs to its section, which is the component that has to be
    // active for the region UI to be revealed.
    $this->clickBuilderButton($button, $this->getSectionRegion($section, $region), $section);
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
   */
  #[When('I :action from the :field_alias form field the layout paragraph in position :position')]
  public function actionOnTopLevelLayoutParagraph(string $action, string $field_alias, string $position): void {
    $this->clickButtonInSection($action, $position, $field_alias);
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
   */
  #[When('I :action from the :fieldAlias field the child :pPosition paragraph of :lPosition layout')]
  public function actionOnChildLayoutParagraph(string $action, string $fieldAlias, string $pPosition, string $lPosition): void {
    $this->clickButtonInParagraph($action, $pPosition, $lPosition, $fieldAlias);
  }

  /**
   * Clicks a button of the builder UI.
   *
   * The builder reveals the UI of a single, "active" component at a time and
   * keeps the controls bar and the insert buttons of every other component in
   * the 'visually-hidden' class, so the component owning the button has to be
   * activated before the button can be reached. Activating it is enough for the
   * controls bar, which the stylesheet only asks to be active. An insert button
   * is asked to be hovered on top of that, or focused, and focus, unlike a
   * hover, survives the next move of the mouse.
   *
   * @param string $button
   *   The button to click, as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::BUTTONS.
   * @param \Behat\Mink\Element\TraversableElement $container
   *   The element holding the button.
   * @param \Behat\Mink\Element\TraversableElement|null $owner
   *   The component that reveals the button when it is active, or NULL for the
   *   buttons of the builder root, which are outside of any component.
   *
   * @see layout_paragraphs/css/builder.css
   * @see layout_paragraphs/js/builder.js
   */
  protected function clickBuilderButton(string $button, TraversableElement $container, ?TraversableElement $owner): void {
    $this->assertCondition(isset(self::BUTTONS[$button]), "The button '$button' is not defined in the context. Available buttons are " . implode(', ', array_keys(self::BUTTONS)) . '.');

    if ($owner !== NULL) {
      $this->activateComponent($owner);
    }

    $link = $container->find('xpath', self::BUTTONS[$button]);
    $this->assertCondition($link !== NULL, "The '$button' button was not found.");
    $link->focus();
    $link->click();
    $this->waitForAjax();

    $this->waitForButtonResult($button);
  }

  /**
   * Waits for the result of clicking a button of the builder UI.
   *
   * @param string $button
   *   The button that was clicked.
   */
  protected function waitForButtonResult(string $button): void {
    switch ($button) {
      case 'edit':
        $this->waitForElementVisible('css', '.ui-dialog-buttonpane button');
        break;

      case 'delete':
        $confirm = $this->waitForElementVisible('css', '.ui-dialog-buttonpane .lpb-btn--confirm-delete');
        $confirm->press();
        $this->waitForAjax();
        $this->waitForNoElement('css', '.lpb-dialog');
        break;

      case 'move up':
      case 'move down':
        // Move actions trigger AJAX then CSS animations.
        // @see layout_paragraphs/js/builder.js move
        $this->waitForCssAnimations();
        break;

      case 'add before':
      case 'add after':
      case 'add component':
        $this->waitForModalToOpen();
        break;
    }
  }

  /**
   * Clicks the button that appends a new component to a container.
   *
   * A container offers its centered add button only while it is empty. Once it
   * holds components, the buttons available are the ones each component keeps
   * before and after itself, so appending to the container means adding after
   * its last component.
   *
   * @param \Behat\Mink\Element\TraversableElement $container
   *   The container to append to: a region, or the builder root.
   * @param string $component_xpath
   *   The xpath that lists the components of the container.
   * @param \Behat\Mink\Element\TraversableElement|null $owner
   *   The component owning the container, or NULL for the builder root.
   */
  protected function clickAddButtonAtEndOf(TraversableElement $container, string $component_xpath, ?TraversableElement $owner): void {
    $components = $container->findAll('xpath', $component_xpath);
    if (empty($components)) {
      $this->clickBuilderButton('add component', $container, $owner);
      return;
    }

    $last = end($components);
    $this->clickBuilderButton('add after', $last, $last);
  }

  /**
   * Asserts the list of top level layout paragraphs.
   *
   * @param string $field_alias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param \Behat\Gherkin\Node\TableNode $layout_list
   *   The table of layout paragraphs. An example is below.
   */
  #[Given('the :field_alias form field should have the following layout paragraphs:')]
  public function theFormFieldShouldHaveTheFollowingLayoutParagraphs(string $field_alias, TableNode $layout_list): void {
    $layout_paragraphs = $this->getFormFieldLayoutParagraphs($field_alias);
    $actual = [];
    /** @var \Behat\Mink\Element\NodeElement $layout_paragraph */
    foreach ($layout_paragraphs as $layout_paragraph) {
      $actual[] = $layout_paragraph->getAttribute('data-layout');
    }
    $expected = array_map(function (string $layout_alias): string {
      $this->assertCondition(isset(self::LAYOUTS_MAP[$layout_alias]), "The layout '$layout_alias' is not defined in the context.");
      return self::LAYOUTS_MAP[$layout_alias];
    }, $layout_list->getColumn(0));

    $this->assertSameValues($expected, $actual);
  }

  /**
   * Asserts the number of paragraphs in the page.
   *
   * @param int $expected_rows
   *   (optional) The expected number of paragraphs. Defaults to 0.
   */
  #[Then('there should be :expected_rows paragraph(s) in the page')]
  #[Then('the page should contain no paragraphs')]
  public function assertParagraphsCountInPage(int $expected_rows = 0): void {
    $this->assertCount($expected_rows, $this->getSession()->getPage()->findAll('xpath', static::PARAGRAPH_ITEM_XPATH));
  }

  /**
   * Asserts the exact text content of the paragraphs in the page.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table of expected paragraph texts.
   */
  #[Given('I should see the following paragraphs in the given order:')]
  public function assertExactParagraphsContentInPage(TableNode $table): void {
    $expected = $table->getColumn(0);
    $paragraphs = $this->getSession()->getPage()->findAll('xpath', static::PARAGRAPH_ITEM_XPATH);
    $actual = array_map(function (TraversableElement $paragraph): string {
      return $paragraph->getText();
    }, $paragraphs);
    $this->assertSameValues($expected, $actual);
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
   */
  #[When('the layout in position :position from the :field_alias form field should have the following structure:')]
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

    $this->assertSameValues($expected, $actual);
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
   * Returns the layout paragraphs builder.
   *
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP. Defaults
   *   to NULL, which returns the only builder in the page.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The builder element.
   */
  protected function getBuilder(?string $field_alias = NULL): TraversableElement {
    $scope = $field_alias === NULL
      ? $this->getSession()->getPage()
      : $this->getParagraphsElement($field_alias);

    $builder = $scope->find('css', '.lp-builder');
    $this->assertCondition($builder !== NULL, 'No layout paragraphs builder was found' . ($field_alias === NULL ? ' in the page.' : " in the '$field_alias' form field."));

    return $builder;
  }

  /**
   * Returns a section of the builder.
   *
   * @param string $position
   *   The position of the section in the builder.
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The section element.
   */
  protected function getSection(string $position, ?string $field_alias = NULL): TraversableElement {
    $sections = $this->getBuilder($field_alias)->findAll('xpath', self::SECTION_XPATH);
    $this->assertCondition(isset($sections[$position - 1]), "There is no section in position $position.");

    return $sections[$position - 1];
  }

  /**
   * Returns a region of a section.
   *
   * @param \Behat\Mink\Element\TraversableElement $section
   *   The section holding the region.
   * @param string|null $region
   *   (optional) The name of the region, e.g. 'first'. Defaults to NULL, which
   *   returns the only region of the section.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The region element.
   */
  protected function getSectionRegion(TraversableElement $section, ?string $region = NULL): TraversableElement {
    if ($region !== NULL) {
      $element = $section->find('css', '.layout__region--' . $region);
      $this->assertCondition($element !== NULL, "The section has no '$region' region.");
      return $element;
    }

    $regions = $section->findAll('css', '.js-lpb-region');
    $this->assertCondition(count($regions) === 1, 'The section has ' . count($regions) . ' regions. Name the region to use.');

    return reset($regions);
  }

  /**
   * Returns the region a component should be added into.
   *
   * @param string $field_alias
   *   The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param string|null $section_position
   *   (optional) The position of the section. Defaults to NULL, which uses the
   *   last region of the builder.
   * @param string|null $region
   *   (optional) The name of the region. Defaults to NULL, which uses the only
   *   region of the section.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The region element.
   */
  protected function getRegionToAddInto(string $field_alias, ?string $section_position = NULL, ?string $region = NULL): TraversableElement {
    if ($section_position !== NULL) {
      return $this->getSectionRegion($this->getSection($section_position, $field_alias), $region);
    }

    // Components can only be added into a region, so without a section the last
    // region of the builder is the one meant.
    $regions = $this->getBuilder($field_alias)->findAll('css', '.js-lpb-region');
    $this->assertCondition(!empty($regions), 'The builder has no region to add a component into.');

    return end($regions);
  }

  /**
   * Returns the component an element belongs to.
   *
   * @param \Behat\Mink\Element\TraversableElement $element
   *   The element to find the owner of.
   *
   * @return \Behat\Mink\Element\TraversableElement|null
   *   The component, or NULL if the element is outside of any component.
   */
  protected function getOwnerComponent(TraversableElement $element): ?TraversableElement {
    return $element->find('xpath', 'ancestor-or-self::div[contains(concat(" ", @class, " "), " js-lpb-component ")][1]');
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
   * Keeps the insert buttons reachable for steps outside of this context.
   *
   * The generic 'I click "Choose component"' step clicks an insert button by
   * its label, without selecting a component first, so the buttons it goes for
   * have to be reachable at all times. The steps of this context select the
   * component that owns the button they are about to click, and do not depend
   * on this.
   *
   * The builder also binds Delete and Backspace on the document to deleting the
   * active component, without checking whether the keystroke was meant for an
   * input. Typing into a dialog field therefore opens the delete confirmation
   * of whatever component is still active behind the dialog, and Mink clears a
   * field by sending backspaces. Unselect the component as soon as a dialog is
   * open, when the builder UI is out of reach anyway. While no dialog is open
   * the component stays active, so the UI a step is about to click keeps
   * working.
   *
   * @see layout_paragraphs/js/builder.js
   */
  #[BeforeStep]
  public function keepBuilderInteractive(BeforeStepScope $scope): void {
    if (!$this->hasJavascriptSupport() || !$this->getSession()->getDriver()->isStarted()) {
      return;
    }
    $this->getSession()->executeScript(<<<'JS'
      if (!document.querySelector('.lp-builder')) {
        return;
      }
      // Add buttons are only revealed while their component is hovered, a state
      // the driver does not hold on to while it checks that a button is visible.
      // An add button also straddles the edge of the component it belongs to, so
      // a paragraph clipping its own overflow, as the iframe one does, cuts its
      // button in half and the driver ends up clicking the component behind it.
      if (!document.getElementById('behat-lpb-visibility')) {
        const style = document.createElement('style');
        style.id = 'behat-lpb-visibility';
        style.textContent =
          '.lp-builder .js-lpb-component > .lpb-btn--add,' +
          '.lp-builder .js-lpb-region > .lpb-btn--add' +
          '{ opacity: 1 !important; }' +
          '.lp-builder .js-lpb-component { overflow: visible !important; }';
        document.head.appendChild(style);
      }
      document.querySelectorAll('.lpb-btn--add.visually-hidden').forEach((button) => {
        button.classList.remove('visually-hidden');
      });
      if (!document.querySelector('.js-lpb-component[data-active="true"]')) {
        return;
      }
      const dialogOpen = [...document.querySelectorAll('.lpb-dialog, .ui-dialog')]
        .some((dialog) => dialog.getClientRects().length > 0);
      if (!dialogOpen) {
        return;
      }
      // A mouseup outside of any component is what unselects it in the builder.
      document.body.dispatchEvent(
        new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window })
      );
JS
    );
  }

  /**
   * Makes a component the active one, revealing its UI elements.
   *
   * Layout Paragraphs shows the UI of a single, "active" component at a time
   * and keeps the controls bar and add buttons of every other component in the
   * 'visually-hidden' class. That is load bearing rather than cosmetic: all
   * controls bars and add buttons are absolutely positioned on the same
   * horizontal center at the same stacking level, so revealing more than one of
   * them at a time makes them overlap and steal each other's clicks.
   *
   * A component becomes active on mouseup, and the handler walks up to the
   * top-most component that is not active yet, which makes selecting a nested
   * component a drill-down: its parent has to be selected first. Replay that
   * here by dispatching a mouseup on every component in the chain, from the
   * outermost one down to the target. Driving the module's own handler keeps
   * its internal state in sync, which setting the attributes would not.
   *
   * @param \Behat\Mink\Element\TraversableElement $component
   *   The component to activate.
   *
   * @see layout_paragraphs/js/builder.js
   */
  protected function activateComponent(TraversableElement $component): void {
    $uuid = $component->getAttribute('data-uuid');
    $this->assertCondition(!empty($uuid), 'The component has no uuid to activate it by.');

    $this->getSession()->executeScript(<<<JS
      (function () {
        const target = document.querySelector('.js-lpb-component[data-uuid="$uuid"]');
        if (!target) {
          return;
        }
        const chain = [];
        for (let el = target; el; el = el.parentElement?.closest('.js-lpb-component')) {
          chain.unshift(el);
        }
        chain.forEach((component) => {
          component.dispatchEvent(
            new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window })
          );
        });
      })();
JS
    );
  }

}
