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

    $field = $this->getParagraphsElement($field_alias);
    $this->getLastAddLink($field, 'Add layout')->click();

    // Wait for modal to be opened.
    $this->waitForModalToOpen();

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

    // Click "Choose component" (opens the modal).
    $this->getChooseComponentLink($field_alias, $layout_position, $region)->click();
    $this->waitForAjax();
    $this->waitForModalToOpen();

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
    $this->assertJavaScriptEnabledBrowser();
    $layout = $this->getFormFieldLayoutParagraphs($field_alias)[$position - 1];

    $this->performAction($layout, $action);
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
    $layoutParagraph = $this->getFormFieldLayoutParagraphs($fieldAlias)[$lPosition - 1];
    $component = $this->getAllSubParagraphsForLayout($layoutParagraph)[$pPosition - 1];

    $this->performAction($component, $action);
  }

  /**
   * Perform an action on a component.
   *
   * @param \Behat\Mink\Element\TraversableElement $component
   *   The component to act upon.
   * @param string $action
   *   Action name.
   */
  protected function performAction(TraversableElement $component, string $action): void {
    $actions = ['delete', 'edit', 'move up', 'move down', 'duplicate'];
    $this->assertCondition(in_array($action, $actions), "The action '$action' is not defined in the context. Available actions are " . implode(', ', $actions));

    // The controls bar only becomes clickable once its component is active.
    $this->activateComponent($component);
    $controls = $component->find('xpath', "./div[contains(concat(' ', @class, ' '), ' lpb-controls ')]");
    $this->assertCondition($controls !== NULL, "The component has no controls bar.");

    $action_link = $controls->findLink(ucfirst($action));
    $this->assertCondition($action_link !== NULL, "Action link '$action' not found.");
    $action_link->click();
    $this->waitForAjax();

    switch ($action) {
      case 'edit':
        $this->waitForElementVisible('css', '.ui-dialog-buttonpane button');
        break;

      case 'delete':
        $btn = $this->waitForElementVisible('css', '.ui-dialog-buttonpane .lpb-btn--confirm-delete');
        $btn->press();
        $this->waitForAjax();
        $this->waitForNoElement('css', '.lpb-dialog');
        break;

      case 'move up':
      case 'move down':
        // Move actions trigger AJAX then CSS animations.
        // @see layout_paragraphs/js/builder.js move
        $this->waitForCssAnimations();
        break;
    }
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
      $layout = $this->getFormFieldLayoutParagraphs($field_alias)[$parent_layout_position - 1];
      // The centered add button of an empty region belongs to the layout, so
      // the layout has to be active before the button reports its real
      // geometry.
      $this->activateComponent($layout);
      $field = $layout->find('css', '.layout__region--' . $parent_layout_region);
    }

    return $this->getLastAddLink($field, 'Choose component');
  }

  /**
   * Returns the last add button in a container, ready to be clicked.
   *
   * Add buttons come in two flavours. The centered one belongs to the container
   * it adds into: the builder root, or a region of a layout. The "before" and
   * "after" ones are prepended and appended to an existing component, and
   * belong to that component. Either way the button is only revealed while the
   * component owning it is the active one, so activate that component here.
   *
   * @param \Behat\Mink\Element\TraversableElement $container
   *   The container holding the add buttons.
   * @param string $label
   *   The label of the add buttons to look for.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The add button.
   *
   * @see \Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder::buildComponent()
   */
  protected function getLastAddLink(TraversableElement $container, string $label): TraversableElement {
    $links = $container->findAll('named', ['link', $label]);
    if (empty($links)) {
      throw new \Exception("No '$label' links found.");
    }

    // Filter out invisible links (that have the style 'display: none'). This is
    // because, when the layout region has at least one paragraph, then the
    // central button that adds to the region is still in the HTML but hidden.
    // The buttons available at that point, are the ones before and after each
    // paragraph.
    $links = array_filter($links, function (TraversableElement $link) {
      $classes = explode(' ', $link->getAttribute('class') ?? '');
      if (in_array('center', $classes, TRUE)) {
        return $link->isVisible();
      }
      return TRUE;
    });
    $this->assertCondition(!empty($links), "No visible '$label' link found.");

    $link = end($links);
    $owner = $link->find('xpath', 'ancestor::div[contains(concat(" ", @class, " "), " js-lpb-component ")][1]');
    if ($owner !== NULL) {
      $this->activateComponent($owner);
    }

    return $link;
  }

  /**
   * Keeps the builder in a state the driver can interact with.
   *
   * Two things need correcting on every step.
   *
   * Steps outside of this context click the add buttons by their link text,
   * without selecting a component first, so the add buttons have to be
   * reachable at all times. Reveals them.
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
