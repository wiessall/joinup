<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementHtmlException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Drupal\DrupalExtension\Context\MinkContext;
use WebDriver\Exception\StaleElementReference;

/**
 * Wait helper methods.
 *
 * @method mixed getContext($class)
 *   Provided by RawDrupalContext on all Behat context classes using this trait.
 *   @see \Drupal\DrupalExtension\Context\RawDrupalContext::getContext()
 */
trait AwaitTrait {

  /**
   * Waits for AJAX to finish.
   */
  protected function waitForAjax(): void {
    $context = $this->getContext(MinkContext::class);
    \assert($context instanceof MinkContext);

    try {
      $context->iWaitForAjaxToFinish();
    }
    catch (UnsupportedDriverActionException $e) {
      // Ignore if the current driver doesn't handle JS.
    }
  }

  /**
   * Waits for the specified element and returns it when available.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param array|string $locator
   *   The selector locator.
   * @param \Behat\Mink\Element\TraversableElement|null $container
   *   (optional) Document to check against, defaults to current page.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 5000 (5s).
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The element node, if found.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the element doesn't exist after the timeout.
   */
  protected function waitForElement(string $selector, array|string $locator, ?TraversableElement $container = NULL, int $timeout = 5000): NodeElement {
    $container = $container ?: $this->getSession()->getPage();
    $element = $container->waitFor($timeout / 1000, function (TraversableElement $container) use ($selector, $locator) {
      return $container->find($selector, $locator);
    });

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', $selector, $locator);
    }
    return $element;
  }

  /**
   * Asserts that no matching element exists after a wait.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param string|array $locator
   *   The selector locator.
   * @param \Behat\Mink\Element\TraversableElement|null $container
   *   (optional) Document to check against, defaults to current page.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 5000 (5s).
   *
   * @throws \Behat\Mink\Exception\ElementHtmlException
   *   When an element still exists after the timeout.
   */
  protected function waitForNoElement(string $selector, array|string $locator, ?TraversableElement $container = NULL, int $timeout = 5000): void {
    $container = $container ?: $this->getSession()->getPage();
    $start = microtime(TRUE);
    $end = $start + ($timeout / 1000);

    do {
      $element = $container->find($selector, $locator);
      if (empty($element)) {
        return;
      }
      usleep(100000);
    } while (microtime(TRUE) < $end);

    throw new ElementHtmlException('Element exists after the specified timeout.', $this->getSession()->getDriver(), $element);
  }

  /**
   * Waits for the specified selector and returns it when available and visible.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param array|string $locator
   *   The selector locator.
   * @param \Behat\Mink\Element\TraversableElement|null $container
   *   (optional) Document to check against, defaults to current page.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 5000 (5s).
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The page element node if found and visible.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the element doesn't exist or is not visible after the timeout.
   */
  protected function waitForElementVisible(string $selector, array|string $locator, ?TraversableElement $container = NULL, int $timeout = 5000): NodeElement {
    $container = $container ?: $this->getSession()->getPage();
    $element = $container->waitFor($timeout / 1000, function (TraversableElement $container) use ($selector, $locator) {
      $element = $container->find($selector, $locator);
      if (!empty($element) && $element->isVisible()) {
        return $element;
      }
      return NULL;
    });

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', $selector, $locator);
    }
    return $element;
  }

  /**
   * Executes a callback until it succeeds or until timeout is hit.
   *
   * @param callable $callback
   *   The callback to execute until it returns a truthy value or timeout.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 5000 (5s).
   *
   * @return mixed
   *   The result of the last invocation of the callback.
   */
  protected function waitUntil(callable $callback, int $timeout = 5000): mixed {
    $end = microtime(TRUE) + ($timeout / 1000);
    do {
      usleep(100000);
      $result = $callback();
    } while (microtime(TRUE) < $end && !$result);

    return $result;
  }

  /**
   * Waits for CSS animations/transitions to finish.
   */
  protected function waitForCssAnimations(): void {
    $this->getSession()->wait(5000, 'document.getAnimations().length === 0');
  }

  /**
   * Waits for the Drupal modal to open.
   *
   * @throws \Exception
   *   Thrown when the modal doesn't open within 10 seconds.
   */
  protected function waitForModalToOpen(): void {
    $this->waitForElementVisible('css', '.ui-dialog-content');
  }

  /**
   * Waits for the Drupal modal to close.
   *
   * @throws \Exception
   *   Thrown when the modal doesn't close within 10 seconds.
   */
  protected function waitForModalToClose(): void {
    $this->waitForNoElement('css', '.ui-dialog-content');
  }

  /**
   * Waits for autocomplete results to appear.
   */
  protected function waitForAutocomplete(): void {
    $result = $this->getSession()->getPage()->waitFor(3, static function (DocumentElement $page): bool {
      try {
        return $page->find('css', '.ui-autocomplete li')?->isVisible() === TRUE;
      }
      catch (StaleElementReference) {
        return FALSE;
      }
    });

    if (!$result) {
      throw new \RuntimeException('Autocomplete results did not appear within 3 seconds.');
    }
  }

}
