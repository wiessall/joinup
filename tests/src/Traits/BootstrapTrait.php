<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Element\NodeElement;

/**
 * Helper methods for interacting with Bootstrap elements.
 */
trait BootstrapTrait {

  /**
   * Opens a Bootstrap accordion.
   *
   * @param \Behat\Mink\Element\NodeElement $wrapper
   *   The element that contains the Bootstrap accordion. Should have the class
   *   'accordion-item'.
   *
   * @throws \RuntimeException
   *   Thrown when the accordion wrapper or button are not found in the page, or
   *   when the accordion doesn't become visible within a reasonable time frame.
   */
  protected function openBootstrapAccordion(NodeElement $wrapper) {
    \assert(method_exists($this, 'hasJavascriptSupport'), __METHOD__ . ' depends on JavascriptTrait. Please include it in your class.');

    // Non-JS browsers are directly interacting with the HTML, so we can
    // consider the content inside the accordion to be visible.
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    // Sanity check. Do we have the correct wrapper passed in?
    if (!$wrapper->hasClass('accordion-item')) {
      throw new \RuntimeException('The accordion wrapper is lacking the \'accordion-item\' class.');
    }

    // If the accordion is already open we don't need to do anything.
    $accordion = $wrapper->find('css', '.accordion-collapse');

    if (!$accordion instanceof NodeElement) {
      throw new \RuntimeException('The accordion was not found in the page.');
    }

    if ($accordion->hasClass('show')) {
      return;
    }

    // Accordion is closed. Hit the button and wait for it to slide open.
    $button = $wrapper->find('css', '.accordion-button');
    if (!$button instanceof NodeElement) {
      throw new \RuntimeException('The button to open the accordion was not found in the page.');
    }
    $button->click();

    $result = $accordion->waitFor(5, function (NodeElement $accordion): bool {
      return $accordion->hasClass('show');
    });

    if (!$result) {
      throw new \RuntimeException('The accordion did not slide open.');
    }
  }

  /**
   * Opens a Bootstrap dropdown.
   *
   * @param \Behat\Mink\Element\NodeElement $wrapper
   *   The element that contains the Bootstrap dropdown. Should have the class
   *   'dropdown'.
   *
   * @throws \RuntimeException
   *   Thrown when the dropdown wrapper or button are not found in the page, or
   *   when the dropdown-menu doesn't become visible within a reasonable time
   *   frame.
   */
  protected function openBootstrapDropdown(NodeElement $wrapper) {
    \assert(method_exists($this, 'hasJavascriptSupport'), __METHOD__ . ' depends on JavascriptTrait. Please include it in your class.');

    // Non-JS browsers are directly interacting with the HTML, so we can
    // consider the content inside the dropdown to be visible.
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    // Sanity check. Do we have the correct wrapper passed in?
    if (!$wrapper->hasClass('dropdown')) {
      throw new \RuntimeException('The dropdown wrapper is lacking the \'dropdown\' class.');
    }

    // If the dropdown is already open we don't need to do anything.
    $dropdown = $wrapper->find('css', '.dropdown-menu');

    if (!$dropdown instanceof NodeElement) {
      throw new \RuntimeException('The dropdown was not found in the page.');
    }

    if ($dropdown->hasClass('show')) {
      return;
    }

    // Dropdown is closed. Hit the button and wait for it to drop down.
    $button = $wrapper->find('css', '.dropdown-toggle');
    if (!$button instanceof NodeElement) {
      throw new \RuntimeException('The button to open the dropdown was not found in the page.');
    }
    $button->click();

    $result = $dropdown->waitFor(5, function (NodeElement $dropdown): bool {
      return $dropdown->hasClass('show');
    });

    if (!$result) {
      throw new \RuntimeException('The dropdown did not drop down.');
    }
  }

}
