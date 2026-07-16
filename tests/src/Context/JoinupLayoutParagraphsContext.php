<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
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
    'add before' => './a[contains(concat(" ", @class, " "), " lpb-btn--add ") and contains(concat(" ", @class, " "), " before ")]',
    'add after' => './a[contains(concat(" ", @class, " "), " lpb-btn--add ") and contains(concat(" ", @class, " "), " after ")]',
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

    $builder = $this->getBuilder($field_alias);
    $this->clickAddButtonAtEndOf($builder, self::SECTION_XPATH);

    $layout = $layout_properties->getRowsHash()['layout'];
    $this->assertCondition(isset(self::LAYOUTS_MAP[$layout]), "The layout '$layout' is not defined in the context.");
    $layout = self::LAYOUTS_MAP[$layout];

    $modalContent = $this->getRegion('Modal content');

    $xpath = "//div[contains(concat(' ', @class, ' '), ' layout-select__item ')]/input[@value='$layout']";
    $modalContent->find('xpath', $xpath)->getParent()->click();
    $this->waitForAjax();

    $modalContent->findButton('Layout Options')->click();
    $modalContent->findButton('Settings')->click();

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

    $save = $this->waitForElementVisible('css', '.ui-dialog-buttonpane .lpb-btn--save');
    $save->press();

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

    $region_element = $this->getRegionToAddInto($field_alias, $layout_position, $region);
    $this->clickAddButtonAtEndOf($region_element, self::REGION_COMPONENT_XPATH);

    $ui_dialog = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $ui_dialog->find('named', ['link', $paragraph_type])->click();
    $this->waitForAjax();

    foreach ($paragraph_fields as $property => $value) {
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
        $this->getSession()->getPage()->selectFieldOption($property, $field_value);
      }
      elseif ($field_type === 'ckeditor') {
        $this->setWysiwygText($property, $field_value);
      }
      else {
        $this->getSession()->getPage()->fillField($property, $field_value);
      }
    }

    $this->getSession()->getPage()->find('css', '.ui-dialog-buttonpane')->pressButton('Save');
    $this->waitForModalToClose();
  }

  /**
   * Opens the chooser that appends a section to the builder.
   *
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP. Defaults
   *   to NULL, which uses the only builder in the page.
   */
  #[When('I add a section')]
  #[When('I add a section to the :field_alias form field')]
  public function addSection(?string $field_alias = NULL): void {
    $this->assertJavaScriptEnabledBrowser();

    $this->clickAddButtonAtEndOf($this->getBuilder($field_alias), self::SECTION_XPATH);
  }

  /**
   * Opens the chooser that appends a component to a region of a section.
   *
   * @param string $position
   *   The position of the section in the builder.
   * @param string|null $region
   *   (optional) The region of the section, e.g. 'first'. Defaults to NULL,
   *   which uses the only region of the section.
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP. Defaults
   *   to NULL, which uses the only builder in the page.
   */
  #[When('I add a component to section :position')]
  #[When('I add a component to the :region region of section :position')]
  #[When('I add a component to the :region region of section :position of the :field_alias form field')]
  public function addComponentToSection(string $position, ?string $region = NULL, ?string $field_alias = NULL): void {
    $this->assertJavaScriptEnabledBrowser();
    $section = $this->getSection($position, $field_alias);

    $this->clickAddButtonAtEndOf($this->getSectionRegion($section, $region), self::REGION_COMPONENT_XPATH);
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

    $this->clickBuilderButton($button, $this->getSection($position, $field_alias));
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
    $this->assertJavaScriptEnabledBrowser();
    $section = $this->getSection($lPosition, $fieldAlias);
    $paragraphs = $this->getAllSubParagraphsForLayout($section);
    $this->assertCondition(isset($paragraphs[$pPosition - 1]), "There is no paragraph in position $pPosition in section $lPosition.");

    $this->clickBuilderButton($action, $paragraphs[$pPosition - 1]);
  }

  /**
   * Clicks a button of the builder UI.
   *
   * The builder reveals the UI of a single, "active" component at a time and
   * keeps the controls bar and the insert buttons of every other component in
   * the 'visually-hidden' class. Focusing the button is what brings it within
   * reach, and it does so on three counts. The builder selects the component
   * owning a UI element that takes the focus, which reveals the element. The
   * stylesheet asks an insert button to be focused, or hovered, and focus,
   * unlike a hover, survives the next move of the mouse. And a focused button
   * is lifted above the rest of the UI, which matters because every controls
   * bar and insert button is absolutely positioned on the same horizontal
   * center at the same stacking level, and so overlaps its neighbours.
   *
   * The driver focuses through a native focus() on the element, which a
   * 'visually-hidden' button, clipped rather than hidden, still accepts.
   *
   * @param string $button
   *   The button to click, as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::BUTTONS.
   * @param \Behat\Mink\Element\TraversableElement $container
   *   The element holding the button.
   *
   * @see layout_paragraphs/css/builder.css
   * @see layout_paragraphs/js/builder.js
   */
  protected function clickBuilderButton(string $button, TraversableElement $container): void {
    $this->assertCondition(isset(self::BUTTONS[$button]), "The button '$button' is not defined in the context. Available buttons are " . implode(', ', array_keys(self::BUTTONS)) . '.');

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
        $this->unselectComponents();
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
        $this->unselectComponents();
        break;
    }
  }

  /**
   * Unselects the component the builder left active behind an open dialog.
   *
   * The builder binds Delete and Backspace on the document to deleting the
   * active component, without checking whether the keystroke was meant for an
   * input. Filling in a dialog field would therefore open the delete
   * confirmation of the component behind the dialog, because Mink clears a
   * field by sending backspaces. Unselecting the component defuses that, and
   * costs nothing: with a dialog open, the builder UI is out of reach anyway.
   *
   * @see layout_paragraphs/js/builder.js
   */
  protected function unselectComponents(): void {
    // A mouseup outside of any component is what unselects it in the builder.
    $this->getSession()->executeScript(<<<'JS'
      document.body.dispatchEvent(
        new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window })
      );
JS
    );
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
   */
  protected function clickAddButtonAtEndOf(TraversableElement $container, string $component_xpath): void {
    $components = $container->findAll('xpath', $component_xpath);
    if (empty($components)) {
      $this->clickBuilderButton('add component', $container);
      return;
    }

    $this->clickBuilderButton('add after', end($components));
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
    $layout_paragraph = $this->getSection($position, $field_alias);
    $actual = [];
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
    $sections = $this->getFormFieldLayoutParagraphs($field_alias);
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

    $regions = $this->getBuilder($field_alias)->findAll('css', '.js-lpb-region');
    $this->assertCondition(!empty($regions), 'The builder has no region to add a component into.');

    return end($regions);
  }

  /**
   * Returns the sections of the builder, i.e. its top level layout paragraphs.
   *
   * @param string|null $field_alias
   *   (optional) The field alias as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP. Defaults
   *   to NULL, which uses the only builder in the page.
   *
   * @return \Behat\Mink\Element\TraversableElement[]
   *   The section elements, in document order.
   */
  protected function getFormFieldLayoutParagraphs(?string $field_alias = NULL): array {
    return $this->getBuilder($field_alias)->findAll('xpath', self::SECTION_XPATH);
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

}
