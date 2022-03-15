<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

/**
 * Support for Javascript testing.
 */
trait JavascriptTrait {

  use BrowserCapabilityDetectionTrait;

  /**
   * Moves a button in the middle of the screen for Javascript tests.
   *
   * In the latest versions of Selenium in Docker, moving to a button element
   * might throw this exception while the button is visible. This might be due
   * to attempting to find the button too fast.
   *
   * @param string $type
   *   The type: link/button.
   * @param string $label
   *   The label of the radio button.
   *
   * @throws \Exception
   *    Thrown when an expected scroll in to view failed.
   */
  protected function scrollLinkOrButtonIntoView(string $type, string $label): void {
    if ($this->browserSupportsJavaScript()) {
      $page = $this->getSession()->getPage();
      $button = $page->find('named', [$type, str_replace('\\"', '"', $label)]);
      if ($button && $id = $button->getAttribute('id')) {
        // This method only works for links and buttons having an ID.
        $this->scrollElementIntoView("#$id");
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
   * @param string $css_selector
   *   The element CSS selector.
   *
   * @throws \Exception
   *    Thrown when an expected scroll in to view failed.
   */
  protected function scrollElementIntoView(string $css_selector): void {
    if ($this->browserSupportsJavaScript()) {
      $this->getSession()->executeScript("(
        function() {
          setTimeout(() => {
            let elem = document.querySelector('$css_selector');
            elem.scrollIntoView({ behavior: 'instant', block: 'center' });
          }, 300);
        }
      )();");
      sleep(1);
    }
  }

}
