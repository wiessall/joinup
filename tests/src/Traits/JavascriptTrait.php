<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Element\NodeElement;

/**
 * Support for Javascript testing.
 */
trait JavascriptTrait {

  use BrowserCapabilityDetectionTrait;

  /**
   * Moves a named element in the middle of the screen for Javascript tests.
   *
   * @param string $type
   *   The type: fieldset, field, link, button, link_or_button, content, select,
   *   checkbox, radio, file, optgroup, option, table, id, id_or_name.
   * @param string $label
   *   The label of the radio button.
   */
  protected function scrollNamedElementIntoView(string $type, string $label): void {
    assert(in_array($type, [
      'fieldset',
      'field',
      'link',
      'button',
      'link_or_button',
      'content',
      'select',
      'checkbox',
      'radio',
      'file',
      'optgroup',
      'option',
      'table',
      'id',
      'id_or_name',
    ], TRUE));

    if ($this->browserSupportsJavaScript()) {
      $page = $this->getSession()->getPage();
      $element = $page->find('named', [$type, str_replace('\\"', '"', $label)]);
      if ($element) {
        $this->scrollElementIntoView($element);
      }
    }
  }

  /**
   * Moves an element identified by CSS selector into view.
   *
   * @param string $css_selector
   *   The element CSS selector.
   */
  protected function scrollCssSelectedElementIntoView(string $css_selector): void {
    if ($this->browserSupportsJavaScript()) {
      $page = $this->getSession()->getPage();
      if ($element = $page->find('css', $css_selector)) {
        $this->scrollElementIntoView($element);
      }
    }
  }

  /**
   * Moves an element in the middle of the screen for Javascript tests.
   *
   * In the latest versions of Selenium in Docker, moving to a button element
   * might throw this exception while the button is visible. This might be due
   * to attempting to find the button too fast.
   *
   * Note that the method is using the Javascript `document.evaluate()` function
   * which is not available in Internet Explorer, but we're using this method
   * only in tests with Mozilla FireFox and Chrome browser.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The node element.
   */
  protected function scrollElementIntoView(NodeElement $element): void {
    $xpath = str_replace("\n", ' ', $element->getXpath());
    $this->getSession()->executeScript("
    (
      function() {
        setTimeout(() => {
          let elem = document.evaluate(\"$xpath\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
          elem.scrollIntoView({ behavior: 'instant', block: 'center' });
        }, 300);
      }
    )();");
    sleep(1);
  }

}
