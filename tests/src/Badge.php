<?php

declare(strict_types = 1);

namespace Drupal\joinup;

use Behat\Mink\Element\NodeElement;

/**
 * Wraps a "badge" element.
 *
 * Chips are depending on JavaScript for their full functionality but they can
 * also have some limited interaction done when JavaScript is disabled, such as
 * verifying their presence or content.
 *
 * This class wraps the Mink NodeElements representing the actual chips and
 * allows to interact with them, regardless of whether they are represented in
 * the DOM as a visible text element (with JS enabled) or as a hidden form
 * element (with JS disabled).
 */
class Badge {

  /**
   * The Mink element representing the badge in the document.
   *
   * @var \Behat\Mink\Element\NodeElement
   */
  protected NodeElement $element;

  /**
   * Whether or not the element can be visually interacted with.
   *
   * @var bool
   */
  protected bool $isVisible;

  /**
   * Constructs a new Badge.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The Mink element that represents the badge in the document.
   * @param bool $isVisible
   *   Whether or not the element can be visually interacted with.
   */
  public function __construct(NodeElement $element, bool $isVisible) {
    $this->element = $element;
    $this->isVisible = $isVisible;
  }

  /**
   * Returns the badge text.
   *
   * @return string
   *   The chip text.
   */
  public function getText(): string {
    return $this->isVisible() ? $this->element->getText() : $this->element->getAttribute('data-description');
  }

  /**
   * Returns whether or not the badge can be visually interacted with.
   *
   * @return bool
   *   TRUE if the badge can be visually interacted with.
   */
  public function isVisible(): bool {
    return $this->isVisible;
  }

  /**
   * Returns the remove button.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The element representing the remove button, or NULL if no remove button
   *   is present on the badge.
   */
  public function getRemoveButton(): ?NodeElement {
    $xpath = "/following-sibling::span[contains(concat(' ', normalize-space(@class), ' '), ' icon--close ')]";
    return $this->element->find('xpath', $xpath);
  }

}
