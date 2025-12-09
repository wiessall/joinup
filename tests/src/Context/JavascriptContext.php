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
   * Restart browser session.
   *
   * Isolates all @javascript scenarios to prevent unwanted browser cache
   * interactions between scenarios and restores the initial window size if the
   * previous scenario changed it.
   * To bypass the browser cache within a single scenario, use the below step
   * definition or \Behat\MinkExtension\Context\MinkContext::reload.
   *
   * @Given I have a new browser session
   *
   * @BeforeScenario @javascript
   */
  public function restartSession(): void {
    $this->getMink()->stopSessions();
  }

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
   * Waits for Slick carousel to initialize and all images to load.
   *
   * @param string $selector
   *   The CSS selector for the slick carousel.
   *
   * @Then I wait for slick carousel :selector to be ready
   */
  public function iWaitForSlickCarousel(string $selector): void {
    $this->waitForElement('css', $selector);

    // Wait for carousel to be initialized.
    $this->waitUntil(function () use ($selector) {
      $initialized = $this->getSession()->evaluateScript(
        "return document.querySelector('$selector') && " .
        "(document.querySelector('$selector').classList.contains('slick--initialized') || " .
        "document.querySelector('$selector').classList.contains('slick-initialized'))"
      );
      return $initialized;
    }, 5000, 'Slick carousel not initialized');

    // Wait for all images in non-cloned slides to load.
    $this->waitUntil(function () use ($selector) {
      $allLoaded = $this->getSession()->evaluateScript(
        "(function() {" .
        "  const images = document.querySelectorAll('$selector .slick-slide:not(.slick-cloned) img');" .
        "  if (images.length === 0) return false;" .
        "  return Array.from(images).every(img => img.complete && img.naturalHeight > 0);" .
        "})()"
      );
      return $allLoaded;
    }, 5000, 'Slick carousel images not loaded');

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
