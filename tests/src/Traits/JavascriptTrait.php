<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Driver\Selenium2Driver;

/**
 * Support for Javascript testing.
 */
trait JavascriptTrait {

  use AwaitTrait;

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

}
