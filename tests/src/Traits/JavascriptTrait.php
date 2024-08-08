<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Driver\Selenium2Driver;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\StaleElementReference;

/**
 * Support for Javascript testing.
 */
trait JavascriptTrait {

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
