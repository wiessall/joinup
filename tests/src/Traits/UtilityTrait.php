<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use PHPUnit\Framework\Assert;

/**
 * Contains utility methods.
 */
trait UtilityTrait {

  /**
   * Explodes and sanitizes a comma separated step argument.
   *
   * @param string $argument
   *   The string argument.
   * @param string $delimiter
   *   The delimiter to use. Defaults to comma.
   *
   * @return array
   *   The argument as array, with trimmed non-empty values.
   */
  protected function explodeDelimitedSeparatedStepArgument(string $argument, string $delimiter = ','): array {
    $argument = explode($delimiter, $argument);
    $argument = array_map('trim', $argument);
    $argument = array_filter($argument);

    return $argument;
  }

  /**
   * Escapes available variables from string and prepares it for a regex match.
   *
   * @param string $string
   *   The string to convert.
   * @param bool $quoteString
   *   Whether to quote the string. Defaults to TRUE.
   *
   * @return string
   *   The converted string.
   */
  protected function escapeStringWithVariables(string $string, bool $quoteString = TRUE): string {
    // The URL structure is very important in some contexts as a wrong URL or an
    // internal path could cause search engines to be misled. Thus, the full URL
    // must be always asserted.
    $baseUrl = $this->getMinkParameter('base_url');

    if ($quoteString) {
      // Escape only special regex characters, not the whole string.
      $string = preg_replace('/([.\\+*?[^\\]$(){}=!<>|:-])/', '\\\\$1', $string);
    }

    $replacements = [
      '__base_url__' => $quoteString ? preg_quote($baseUrl) : $baseUrl,
      '__random_text__' => '([^/]*)?',
      '__timezone__' => '\d{2}',
    ];
    return strtr($string, $replacements);
  }

  /**
   * Checks if an element is visible for human eyes.
   *
   * To enable certain elements to be visible for screen readers but not for
   * human eyes, Drupal provides the 'visually-hidden' class. This uses a trick
   * that fools the browser in thinking this element is still visible, even
   * though it doesn't actually show up visually in the page.
   *
   * This method will check both that the browser reports this element to be
   * visible, and that the 'visually-hidden' class is absent.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element to check for visibility.
   *
   * @throws \Exception
   *   Thrown if the element is not visible.
   */
  protected function assertVisuallyVisible(NodeElement $element): void {
    Assert::assertTrue($this->isVisuallyVisible($element), 'The element is visually visible');
  }

  /**
   * Checks if an element is invisible for human eyes.
   *
   * To enable certain elements to be visible for screen readers but not for
   * human eyes, Drupal provides the 'visually-hidden' class. This uses a trick
   * that fools the browser in thinking this element is still visible, even
   * though it doesn't actually show up visually in the page.
   *
   * This method will check first that the browser reports this element to be
   * invisible; if that doesn't work, then it checks if the 'visually-hidden'
   * class is present.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element to check for visibility.
   *
   * @throws \Exception
   *   Thrown if the element is visible.
   */
  protected function assertNotVisuallyVisible(NodeElement $element): void {
    Assert::assertFalse($this->isVisuallyVisible($element), 'The element is not visually visible');
  }

  /**
   * Returns if an element is visible for human eyes.
   *
   * To enable certain elements to be visible for screen readers but not for
   * human eyes, Drupal provides the 'visually-hidden' class. This uses a trick
   * that fools the browser in thinking this element is still visible, even
   * though it doesn't actually show up visually in the page.
   *
   * This method will check both that the browser reports this element to be
   * visible, and that the 'visually-hidden' class is absent.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element to check.
   *
   * @return bool
   *   True if human optical receptors will be able to detect this particular
   *   element.
   *
   * @throws \Exception
   *   Thrown if the browser does not support JavaScript or if the passed in
   *   element is no longer present on the page.
   */
  protected function isVisuallyVisible(NodeElement $element): bool {
    \assert(method_exists($this, 'assertJavaScriptEnabledBrowser'), __METHOD__ . ' depends on BrowserCapabilityDetectionTrait. Please include it in your class.');
    // This only works on JS-enabled browsers.
    $this->assertJavaScriptEnabledBrowser();
    return $this->getMink()->getSession()->getDriver()->isVisible($element->getXpath());
  }

  /**
   * Converts property values of an object to the ones defined in a mapping.
   *
   * Useful to convert human-readable strings used in tests to machine-readable
   * ones.
   *
   * @param object $object
   *   The object itself.
   * @param string $property
   *   The property name.
   * @param array $mapping
   *   An array of mapped values, where keys are the human-readable strings.
   */
  protected static function convertObjectPropertyValues($object, string $property, array $mapping): void {
    if (!property_exists($object, $property)) {
      return;
    }

    // Force the use of human readable values in Behat test scenarios, throw
    // an exception if the numeric values are used.
    if (!array_key_exists($object->$property, $mapping)) {
      $supported_values = implode(', ', array_keys($mapping));
      throw new \UnexpectedValueException("Unexpected value for {$property} '{$object->$property}'. Supported values are: $supported_values.");
    }

    // Replace the human readable value with the expected boolean.
    $object->$property = $mapping[$object->$property];
  }

  /**
   * Converts an ordinal number (1st, 2nd, 17th etc) to a normal number.
   *
   * @param string $number
   *   The ordinal number.
   *
   * @return int
   *   The number.
   *
   * @throws \Exception
   *   Thrown if the number is not an ordinal.
   */
  protected function convertOrdinalToNumber(string $number): int {
    $return = preg_replace('/\\b(\d+)(?:st|nd|rd|th)\\b/', '$1', $number);
    if (!is_numeric($return)) {
      throw new \Exception("Could not convert {$number} to a number.");
    }
    return (int) $return;
  }

  /**
   * Asserts a condition, throwing an ExpectationException on failure.
   *
   * Mirrors the WebAssert::assert() pattern but uses the native Behat/Mink
   * exception so no PHPUnit runner initialization is required.
   *
   * @param bool $condition
   *   The condition to assert.
   * @param string $message
   *   The failure message shown in the Behat report.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Thrown when the condition is FALSE.
   */
  protected function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }
  }

}
