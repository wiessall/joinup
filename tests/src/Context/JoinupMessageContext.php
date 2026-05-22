<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Temporary behat step definition to check presence of inline form errors.
 *
 * @todo Contribute this in ISAICP-0000.
 */
class JoinupMessageContext extends RawDrupalContext {

  /**
   * Selector of inline form error messages.
   */
  private const string SELECTOR = '.form-item--error-message, .form-item__error-message';

  /**
   * Checks if the current page contains the given test as an inline form error.
   *
   * @param string $message
   *   The inline form error message which should be present.
   */
  #[Then('I should see the inline form error( message) :message')]
  public function assertInlineFormError(string $message): void {
    // There must be also a "regular" error message on the page.
    $errorSelector = $this->getParameter('selectors')['messages']['error'];
    $errorElements = $this->getSession()->getPage()->findAll('css', $errorSelector);
    if (empty($errorElements)) {
      throw new ExpectationException(
        sprintf(
          "The page '%s' does not contain any error message.",
          $this->getSession()->getCurrentUrl(),
        ),
        $this->getSession()->getDriver(),
      );
    }

    // Check whether the "<n> errors have been found: <label-1>, <label-2>"
    // error message is present.
    $mainErrorMessageFound = array_reduce(
      $errorElements,
      function (bool $carry, NodeElement $element): bool {
        return $carry ||
          preg_match('#\d+ errors? ha(?:s|ve) been found:#', $element->getText());
      },
      FALSE
    );
    if (!$mainErrorMessageFound) {
      throw new ExpectationException(
        sprintf(
          "The page '%s' does not contain the main error message of Inline Form Errors.",
          $this->getSession()->getCurrentUrl()
        ),
        $this->getSession()->getDriver(),
      );
    }

    $this->assert($message);
  }

  /**
   * Checks if the current page does not contain the given error message.
   *
   * @param string $message
   *   The text to be checked.
   */
  #[Given('I should not see the inline form error message :message')]
  public function assertNotErrorVisible(string $message): void {
    $this->assertNot($message);
  }

  /**
   * Checks if the current page contains the given set of inline form errors.
   *
   * @param \Behat\Gherkin\Node\TableNode $messages
   *   The table of texts to be checked. The first row should consist of the
   *   string "Error messages".
   */
  #[Then('I should see the following inline form error messages:')]
  public function assertMultipleErrors(TableNode $messages): void {
    $this->assertValidMessageTable($messages, 'error messages');
    foreach ($messages->getHash() as $value) {
      $value = array_change_key_case($value);
      $message = trim($value['error messages']);
      $this->assertInlineFormError($message);
    }
  }

  /**
   * Checks whether the given message table is valid.
   *
   * Copy of MessageContext::assertValidMessageTable().
   *
   * @param \Behat\Gherkin\Node\TableNode $messages
   *   The list of messages.
   * @param string $expected_header
   *   The header that should be present in the list.
   *
   * @see \Drupal\DrupalExtension\Context\MessageContext::assertValidMessageTable()
   */
  protected function assertValidMessageTable(TableNode $messages, string $expected_header): void {
    // Check that the table only contains a single column.
    $header_row = $messages->getRow(0);

    $column_count = count($header_row);
    if ($column_count != 1) {
      throw new \RuntimeException("The list of $expected_header should only contain 1 column. It has $column_count columns.");
    }

    // Check that the correct header is used.
    $actual_header = reset($header_row);
    if (strtolower(trim($actual_header)) !== $expected_header) {
      $capitalized_header = ucfirst($expected_header);
      throw new \RuntimeException("The list of $expected_header should have the header '$capitalized_header'.");
    }
  }

  /**
   * Checks whether a text appears inside an element.
   *
   * This is basically the simplified copy of MessageContext::assert().
   *
   * @param string $message
   *   The message to be checked.
   *
   * @see \Drupal\DrupalExtension\Context\MessageContext::assert()
   */
  private function assert(string $message): void {
    // @todo Create a standalone inline form error selector option in
    //   \Drupal\DrupalExtension\ServiceContainer\DrupalExtension::configure.
    $selectorObjects = $this->getSession()->getPage()->findAll('css', self::SELECTOR);

    if (empty($selectorObjects)) {
      throw new ExpectationException(
        sprintf(
          "The page '%s' does not contain any inline form errors",
          $this->getSession()->getCurrentUrl()
        ),
        $this->getSession()->getDriver(),
      );
    }

    foreach ($selectorObjects as $selectorObject) {
      if (str_contains(trim($selectorObject->getText()), $message)) {
        return;
      }
    }

    throw new ExpectationException(
      sprintf(
        "The page '%s' does not contain the inline form error '%s'",
        $this->getSession()->getCurrentUrl(),
        $message,
      ),
      $this->getSession()->getDriver(),
    );
  }

  /**
   * Checks whether a text does not appear inside an element.
   *
   * This is basically the simplified copy of MessageContext::assertNot().
   *
   * @param string $message
   *   The message to be checked.
   *
   * @see \Drupal\DrupalExtension\Context\MessageContext::assertNot()
   */
  private function assertNot(string $message): void {
    $selectorObjects = $this->getSession()->getPage()->findAll('css', self::SELECTOR);
    if (empty($selectorObjects)) {
      return;
    }

    foreach ($selectorObjects as $selectorObject) {
      if (!str_contains(trim($selectorObject->getText()), $message)) {
        continue;
      }

      throw new ExpectationException(
        sprintf(
          "The page '%s' contains the error message '%s', but it should not.",
          $this->getSession()->getCurrentUrl(),
          $message
        ),
        $this->getSession()->getDriver(),
      );
    }
  }

}
