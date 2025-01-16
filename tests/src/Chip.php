<?php

declare(strict_types=1);

namespace Drupal\joinup;

use Behat\Mink\Element\NodeElement;

/**
 * Wraps a "chip" element.
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
readonly class Chip {

  public function __construct(
    protected NodeElement $element,
    protected bool $isVisible,
  ) {
  }

  /**
   * Returns the chip text.
   *
   * @return string
   *   The chip text.
   */
  public function getText(): string {
    return $this->isVisible() ? $this->element->getText() : $this->element->getAttribute('data-joinup-description');
  }

  /**
   * Returns whether or not the chip can be visually interacted with.
   *
   * @return bool
   *   TRUE if the chip can be visually interacted with.
   */
  public function isVisible(): bool {
    return $this->isVisible;
  }

  /**
   * Returns the remove button.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The element representing the remove button, or NULL if no remove button
   *   is present on the chip.
   */
  public function getRemoveButton(): ?NodeElement {
    $xpath = "//button[contains(concat(' ', normalize-space(@class), ' '), ' icon--close ')] | //span[contains(concat(' ', normalize-space(@class), ' '), ' icon--close ')]";
    return $this->element->find('xpath', $xpath);
  }

}
