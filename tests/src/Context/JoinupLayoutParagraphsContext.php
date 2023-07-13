<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

/**
 * Behat step definitions for layout paragraphs.
 */
class JoinupLayoutParagraphsContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Field mapping.
   *
   * @var array
   */
  const FIELD_MAP = [
    'Custom page body' => 'field-paragraphs-body',
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
    $this->getChooseComponentLink($field_alias)->click();

    // An overlay has opened and we need to select "Layout".
    $xpath = '//div[contains(concat(" ", @class, " "), " lpb-component-list__group--layout ")]';
    $this->getSession()->wait(5000, "document.querySelector('div.lpb-component-list__group--layout') !== null");
    $layouts_container = $this->getSession()->getPage()->find('xpath', $xpath);
    $layouts_container->clickLink('Layout');

    // Get the 'layout' property from the table.
    $layout = $layout_properties->getRowsHash()['layout'];
    assertTrue(isset(self::LAYOUTS_MAP[$layout]), "The layout '$layout' is not defined in the context.");
    $layout = self::LAYOUTS_MAP[$layout];

    // Select the layout.
    $xpath = "//div[contains(concat(' ', @class, ' '), ' layout-select__item ')]/input[@value='$layout']";
    $this->getSession()->wait(5000, "document.querySelector('div.layout-select__item input[value=\"$layout\"]') !== null");
    $this->getSession()->getPage()->find('xpath', $xpath)->getParent()->click();
    // Wait until AJAX is done.
    $this->getSession()->wait(5000, "jQuery.active === 0");

    // Expand both the "Layout options" and "Settings" fieldsets. The classes
    // are details[id="layout-paragraphs-layout-options"] and
    // details[data-drupal-selector="edit-behavior-plugins"]. They both have a
    // summary of role="button" to click.
    $xpath = '//details[@data-drupal-selector="layout-paragraphs-layout-options"]//summary[@role="button"]';
    $this->getSession()->getPage()->find('xpath', $xpath)->click();
    $xpath = '//details[@data-drupal-selector="edit-behavior-plugins"]//summary[@role="button"]';
    $this->getSession()->getPage()->find('xpath', $xpath)->click();

    // Fill in the available fields.
    foreach ($layout_properties->getRowsHash() as $property => $value) {
      if ($property == 'layout') {
        continue;
      }

      assertTrue(isset(self::LAYOUT_PROPERTIES_MAP[strtolower($property)]), "The layout property '$property' is not defined in the context.");
      $type = self::LAYOUT_PROPERTIES_MAP[strtolower($property)];
      if ($type == 'select') {
        $this->getSession()->getPage()->selectFieldOption($property, $value);
      }
      else {
        $this->getSession()->getPage()->fillField($property, $value);
      }
    }

    // Finally, click the "Save" button to add the paragraph.
    $this->getSession()->getPage()->find('css', '.ui-dialog-buttonpane')->pressButton('Save');
    // Wait until the button pane is gone.
    $this->getSession()->wait(5000, "document.querySelector('.ui-dialog-buttonpane') === null");
  }

  /**
   * Removes a layout paragraph in the given field.
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
  public function removeTopLevelLayoutParagraph(string $action, string $field_alias, string $position): void {
    $actions = ['delete', 'edit', 'move up', 'move down', 'duplicate'];
    assertTrue(in_array($action, $actions), "The action '$action' is not defined in the context. Available actions are ", implode(', ', $actions));

    $this->assertJavaScriptEnabledBrowser();
    $this->getFormFieldLayoutParagraphs($field_alias)[$position - 1]->clickLink(ucfirst($action));
    if ($action === 'delete') {
      $this->getSession()->wait(5000, "document.querySelector('.lpb-dialog') !== null");
      $this->getSession()->getPage()->find('css', '.ui-dialog-buttonpane')->pressButton('Delete');
      $this->getSession()->wait(5000, "document.querySelector('.lpb-dialog') === null");
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
   * Clicks on link inside paragraph.
   *
   * @param string $label
   *   The label of the field.
   * @param string $field
   *   The field in which to take action.
   * @param int $position
   *   The ordinal position of the field.
   *
   * @When I click :label in the :field for paragraph layout :position
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function iClickInTheFieldForParagraphLayout(string $label, string $field, int $position): void {
    $paragraphItem = $this->getParagraphsElementItem($field, $position);
    $this->scrollElementIntoView($paragraphItem);
    $paragraphItem->clickLink($label);
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
   * Returns a list of layout paragraphs elements..
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
    $xpath = '//div[contains(concat(" ", @class, " "), " paragraph ")]';
    $elements = $field->findAll('xpath', $xpath);
    return $elements ?? [];
  }

  /**
   * Returns a layout paragraph element by uuid.
   *
   * @param string $field_alias
   *   The field designator as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param string $uuid
   *   The uuid of the paragraph.
   *
   * @return \Behat\Mink\Element\TraversableElement|null
   *   The layout paragraph element or null if not found.
   */
  protected function findLayoutParagraphByUuid(string $field_alias, string $uuid): ?TraversableElement {
    $paragraphs = $this->getFormFieldLayoutParagraphs($field_alias);
    $element = NULL;
    foreach ($paragraphs as $paragraph) {
      if ($paragraph->hasAttribute('data-uuid') && $paragraph->getAttribute('data-uuid') === $uuid) {
        $element = $paragraph;
        break;
      }
    }
    return $element;
  }

  /**
   * Returns a layout paragraph element by position.
   *
   * @param string $field_alias
   *   The field designator as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param int $position
   *   The position of the paragraph.
   *
   * @return \Behat\Mink\Element\TraversableElement|null
   *   The layout paragraph element or null if not found.
   */
  protected function findLayoutParagraphByPosition(string $field_alias, int $position): ?TraversableElement {
    $paragraphs = $this->getFormFieldLayoutParagraphs($field_alias);
    return $paragraphs[$position] ?? NULL;
  }

  /**
   * Returns the add component button.
   *
   * @param string $field_alias
   *   The field designator as defined in
   *   \Drupal\joinup\Context\JoinupLayoutParagraphsContext::FIELD_MAP.
   * @param int $position
   *   (optional) The position of the paragraph. Defaults to -1, which means the
   *   last button.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The add component button.
   */
  protected function getChooseComponentLink(string $field_alias, int $position = -1): TraversableElement {
    $links = $this->getSession()->getPage()->findAll('named', [
      'link',
      'Choose component',
    ]);
    if (empty($links)) {
      throw new \Exception('No add component links found.');
    }

    if (!isset($links[$position]) && $position !== -1) {
      throw new \Exception("No add component link found at position $position.");
    }

    return $position === -1 ? end($links) : $links[$position];
  }

}
