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
   * Waits for Slick carousel to be fully initialized.
   *
   * Ensures carousel is ready by verifying initialization class, slides,
   * loaded images, animations complete, and document ready state.
   *
   * @param string $selector
   *   The CSS selector for the slick container.
   *
   * @throws \Exception
   *   Thrown when carousel doesn't initialize after 10 retries (5 seconds).
   *
   * @Then I wait for slick carousel :selector to be ready
   */
  public function iWaitForSlickCarousel(string $selector): void {
    $this->waitForElement('css', $selector);

    // Retry up to 10 times with 500ms delay.
    for ($i = 0; $i < 10; $i++) {
      $state = $this->getSession()->evaluateScript($this->getSlickStateScript($selector));

      // Check if carousel is fully ready.
      if ($state['initialized'] && $state['slideCount'] > 0 &&
          $state['totalImages'] > 0 && $state['imagesLoaded'] === $state['totalImages']) {
        $this->waitForCssAnimations();
        $this->waitUntil(fn() => $this->getSession()->evaluateScript('return document.readyState') === 'complete', 2000);
        return;
      }

      if ($i < 9) {
        usleep(500000);
      }
    }

    // Build detailed error message with diagnosis.
    $state = $this->getSession()->evaluateScript($this->getSlickStateScript($selector));

    // Determine the specific issue for better debugging.
    $issue = !$state['exists'] ? 'Element not found in DOM' :
             (!$state['initialized'] ? 'Slick not initialized (missing slick--initialized class)' :
             ($state['slideCount'] === 0 ? 'No slides found' :
             ($state['totalImages'] === 0 ? 'No images in slides' :
             'Images still loading')));

    throw new \Exception(sprintf(
      "Slick carousel '%s' not ready after 5 seconds.\nIssue: %s\nState: initialized=%s, slides=%d, images_loaded=%d/%d",
      $selector,
      $issue,
      $state['initialized'] ? 'yes' : 'no',
      $state['slideCount'] ?? 0,
      $state['imagesLoaded'] ?? 0,
      $state['totalImages'] ?? 0
    ));
  }

  /**
   * Returns JavaScript to check Slick carousel state.
   *
   * @param string $selector
   *   The CSS selector for the slick container.
   *
   * @return string
   *   JavaScript code that returns carousel state object.
   */
  private function getSlickStateScript(string $selector): string {
    return <<<JS
      (function() {
        const el = document.querySelector('$selector');
        if (!el) return {initialized: false, exists: false, slideCount: 0, imagesLoaded: 0, totalImages: 0};

        const slides = el.querySelectorAll('.slick-slide:not(.slick-cloned)');
        const images = el.querySelectorAll('.slick-slide:not(.slick-cloned) img');
        const loaded = Array.from(images).filter(i => i.complete && i.naturalHeight > 0).length;

        return {
          initialized: jQuery(el).hasClass('slick--initialized') || jQuery(el).hasClass('slick-initialized'),
          exists: true,
          slideCount: slides.length,
          imagesLoaded: loaded,
          totalImages: images.length
        };
      })();
JS;
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
