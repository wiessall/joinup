<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;

/**
 * Javascript related behat step definitions.
 */
class JavascriptContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Waits until an element with specified CSS exists on page.
   *
   * @param string $selector
   *   The CSS selector.
   *
   * @Then I wait until :selector element exists
   */
  public function iWaitForElement(string $selector): void {
    $this->waitForElement('css', $selector);
  }

  /**
   * Waits until no elements with specified CSS exists on page.
   *
   * @param string $selector
   *   The CSS selector.
   *
   * @Then I wait until no :selector element exists
   */
  public function iWaitForNoElement(string $selector): void {
    $this->waitForNoElement('css', $selector);
  }

  /**
   * Waits for CSS animations/transitions to finish.
   *
   * @Then I wait for CSS animations to finish
   */
  public function iWaitForCssAnimations(): void {
    $this->waitForCssAnimations();
  }

  /**
   * Asserts that the default Drupal modal is opened.
   *
   * @throws \Exception
   *   Thrown when the modal doesn't open within 5 seconds.
   *
   * @Then a modal should/will open
   */
  public function iWaitForDrupalModalToOpen(): void {
    $this->waitForModalToOpen();
  }

  /**
   * Asserts that the default Drupal modal is closed.
   *
   * @throws \Exception
   *   Thrown when the modal doesn't close within 5 seconds.
   *
   * @Then the modal should be closed
   */
  public function iWaitForDrupalModalToClose(): void {
    $this->waitForModalToClose();
  }

}
