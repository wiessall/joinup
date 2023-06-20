<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;

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

}
