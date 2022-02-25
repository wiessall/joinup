<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

/**
 * Helper methods to interact with the browser window.
 */
trait BrowserWindowTrait {

  use BrowserCapabilityDetectionTrait;

  /**
   * Scrolls to the top of the browser window.
   */
  protected function scrollToTop(): void {
    if ($this->browserSupportsJavaScript()) {
      $this->getSession()->getDriver()->executeScript('window.scroll(0, 0);');
    }
  }

}
