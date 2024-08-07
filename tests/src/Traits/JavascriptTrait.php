<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\TraversableElement;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\StaleElementReference;

/**
 * Support for Javascript testing.
 */
trait JavascriptTrait {

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
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    // Strings (labels) containing simple quotes (') are enclosed with double
    // quotes ("), thus we need to escape them. Also, xpath might contain new
    // lines which we replace with spaces.
    $xpath = str_replace(['"', "\n"], ['\"', ' '], $element->getXpath());
    $this->getSession()->executeScript("
      let element = document.evaluate(\"$xpath\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
      element.scrollIntoView({ behavior: 'instant', block: 'center' });
    ");
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

  /**
   * Checks that we are running on a JavaScript-enabled browser.
   *
   * @throws \LogicException
   *   Thrown when not running on a JS-enabled browser.
   */
  protected function assertJavaScriptEnabledBrowser(): void {
    if (!$this->hasJavascriptSupport()) {
      // Show a helpful error message.
      throw new \LogicException('This test needs to run on a real browser using Selenium or similar. Please add the "@javascript" tag to the scenario.');
    }
  }

  /**
   * Scrolls to the top of the browser's window.
   */
  protected function scrollToTop(): void {
    if ($this->hasJavascriptSupport()) {
      $this->getSession()->getDriver()->executeScript('window.scroll(0, 0);');
    }
  }

  /**
   * Asserts that the default Drupal modal is opened.
   *
   * @throws \Exception
   *   Thrown when the modal doesn't open within 5 seconds.
   */
  protected function assertModalToOpen(): void {
    // A modal can be opened only in a JavaScript-enabled browser.
    self::assertJavaScriptEnabledBrowser();

    $result = $this->getSession()->getPage()->waitFor(5, function (): bool {
      // Retrieve again a fresh copy of the page element at each cycle.
      $modal = $this->getSession()
        ->getPage()
        ->find('css', '.ui-dialog-content');
      return $modal && $modal->isVisible();
    });

    if (!$result) {
      throw new \Exception('The modal did not open.');
    }
  }

  /**
   * Asserts that the default Drupal modal is closed.
   *
   * @throws \Exception
   *   Thrown when the modal doesn't close within 5 seconds.
   */
  protected function assertModalToClose(): void {
    // A modal can be opened only in a JavaScript-enabled browser.
    self::assertJavaScriptEnabledBrowser();

    $result = $this->getSession()->getPage()->waitFor(5, function (): bool {
      try {
        // Normally the modal should be completely removed from the DOM,
        // but sometimes the markup is kept in the DOM as hidden.
        // @see Drupal.AjaxCommands.prototype.closeDialog()
        $modal = $this->getSession()
          ->getPage()
          ->find('css', '.ui-dialog-content');
        return !$modal || !$modal->isVisible();
      }
      catch (NoSuchElement $e) {
        // The modal has been completely removed from the DOM.
        return TRUE;
      }
      catch (StaleElementReference $e) {
        // The element went stale in the time between fetching it and actually
        // checking its visibility. The next check should launch a NoSuchElement
        // exception.
        return FALSE;
      }
    });

    if (!$result) {
      throw new \Exception('The modal did not close.');
    }
  }

  /**
   * Asserts that a JS condition completes within a specified time frame.
   *
   * @param int $seconds
   *   Number of seconds to wait.
   * @param string $script
   *   The JS condition/callback to run.
   */
  protected function assertWaitJs(int $seconds, $script): void {
    self::assertJavaScriptEnabledBrowser();

    $res = $this->getSession()->wait(1000 * $seconds, $script);
    if (!$res) {
      throw new \Exception(sprintf("The JavaScript condition/callback was not met after '%s' seconds.", $seconds));
    }
  }

}
