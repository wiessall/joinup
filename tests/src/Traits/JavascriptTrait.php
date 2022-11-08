<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\TraversableElement;

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
   * @param \Behat\Mink\Element\TraversableElement|null $container
   *   A traversable element to restrict the search to. Defaults to page.
   */
  protected function scrollNamedElementIntoView(string $type, string $label, ?TraversableElement $container = NULL): void {
    if (!$this->hasJavascriptSupport()) {
      return;
    }
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

    if ($container) {
      $this->scrollElementIntoView($container);
    }
    $container ??= $this->getSession()->getPage();
    $element = $container->find('named', [
      $type, str_replace('\\"', '"', $label),
    ]);
    if ($element) {
      $this->scrollElementIntoView($element);
    }
  }

  /**
   * Moves an element identified by CSS selector into view.
   *
   * @param string $css_selector
   *   The element CSS selector.
   * @param \Behat\Mink\Element\TraversableElement|null $container
   *   A traversable element to restrict the search to. Defaults to page.
   */
  protected function scrollCssSelectedElementIntoView(string $css_selector, ?TraversableElement $container = NULL): void {
    if (!$this->hasJavascriptSupport()) {
      return;
    }
    $container ??= $this->getSession()->getPage();
    if ($element = $container->find('css', $css_selector)) {
      $this->scrollElementIntoView($element);
    }
  }

  /**
   * Moves an element in the middle of the screen for Javascript tests.
   *
   * In the latest versions of Selenium in Docker, moving to a button element
   * might throw this exception when the button is not visible in the browser
   * viewport.
   *
   * Note that the method is using the Javascript `document.evaluate()` function
   * which is not available in Internet Explorer, but we're using this method
   * only in tests with Mozilla FireFox and Chrome browser.
   *
   * @param \Behat\Mink\Element\TraversableElement $element
   *   The node element.
   */
  protected function scrollElementIntoView(TraversableElement $element): void {
    if (!$this->browserSupportsJavaScript()) {
      return;
    }

    // Strings (labels) containing simple quotes (') are enclosed with double
    // quotes ("), thus we need to escape them. Also, xpath might contain new
    // lines which we replace with spaces.
    $xpath = str_replace(['"', "\n"], ['\"', ' '], $element->getXpath());
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

  /**
   * Detects whether this test has Javascript support.
   *
   * @return bool
   *   Whether this test has Javascript support.
   */
  public function hasJavascriptSupport(): bool {
    return $this->getSession()->getDriver() instanceof Selenium2Driver;
  }

}
