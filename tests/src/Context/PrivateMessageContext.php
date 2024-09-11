<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;

/**
 * Behat step definitions for testing private messages.
 */
class PrivateMessageContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Checks that the specified number of private messages are present.
   *
   * @param int $expectedCount
   *   The expected number of private messages.
   *
   * @throws \Exception
   *   Thrown when the actual count does not match the expected count.
   *
   * @Then I should see :expectedCount private message(s)
   */
  public function iShouldSeePrivateMessages(int $expectedCount): void {
    $page = $this->getSession()->getPage();
    $messagesContainer = $page->find('css', '.private-message-thread-messages .field__items');

    if (empty($messagesContainer) && $expectedCount > 0) {
      throw new \Exception('Private messages were not found in the page.');
    }
    elseif ($expectedCount === 0) {
      return;
    }

    $messageElements = $messagesContainer->findAll('css', '.private-message-wrapper');

    $actualCount = count($messageElements);
    if ($actualCount !== $expectedCount) {
      throw new \Exception(sprintf('Expected to find %d private messages, but found %d.', $expectedCount, $actualCount));
    }
  }

  /**
   * Verifies that a private message contains the specified text.
   *
   * @param string $messageText
   *   The text expected to be found in a private message.
   *
   * @throws \Exception
   *   Thrown when the text is not found in any private message.
   *
   * @Then I should see a private message containing the text :messageText
   */
  public function iShouldSeePrivateMessageContainingText(string $messageText): void {
    $messagesContainer = $this->getMessagesContainer();

    $messageElements = $messagesContainer->findAll('css', '.private-message-wrapper');

    foreach ($messageElements as $messageElement) {
      if (str_contains($messageElement->getText(), $messageText)) {
        return;
      }
    }

    throw new \Exception(sprintf('The text "%s" was not found in any private message.', $messageText));
  }

  /**
   * Checks that multiple texts are present within private messages.
   *
   * Example table format:
   * | Message one     |
   * | Message 2 reply |
   * | Another message |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table node containing the texts to be checked.
   *   Each row should contain one text string.
   *
   * @throws \Exception
   *   Thrown when any of the specified texts are not
   *   found in the private messages.
   *
   * @Then I should see the following texts in private messages:
   */
  public function iShouldSeeTheFollowingTextsInPrivateMessages(TableNode $table): void {
    $messagesContainer = $this->getMessagesContainer();

    $messageText = $messagesContainer->getText();
    $texts = $table->getColumn(0);

    foreach ($texts as $text) {
      if (strpos($messageText, $text) === FALSE) {
        throw new \Exception(sprintf('The text "%s" was not found in any private message.', $text));
      }
    }
  }

  /**
   * Checks that the notification shows the correct number of unread threads.
   *
   * @param int $expectedCount
   *   The expected number of unread private message threads.
   *
   * @throws \Exception
   *   Thrown when the actual count does not match the expected count.
   *
   * @Then I should see :expectedCount unread private message(s)
   */
  public function iShouldSeeUnreadPrivateMessageThreads(int $expectedCount): void {
    $page = $this->getSession()->getPage();
    $notificationElement = $page->find('css', '.private-message-notification-wrapper .private-message-page-link');

    if (NULL === $notificationElement) {
      throw new \Exception('The private message notification element was not found.');
    }

    $actualCount = (int) $notificationElement->getText();
    if ($actualCount !== $expectedCount) {
      throw new \Exception(sprintf('Expected to see %d unread private message thread(s), but found %d.', $expectedCount, $actualCount));
    }
  }

  /**
   * Simulates picking of an autosuggestion value from a field.
   *
   * @param string $value
   *   The value to select from suggestions.
   *
   * @throws \Exception
   *   Thrown when the field is not found, the field is not an autocomplete
   *   field, when there was a failure retrieving the autocomplete suggestions
   *   or if the wanted suggestion is not found.
   *
   * @When I pick :value from the private message user autocomplete suggestions
   */
  public function fillPersonFieldWithNormalisedEntityReference(string $value): void {
    $this->assertJavaScriptEnabledBrowser();
    $xpath = '//div[@id="pm-members-autocomplete-results-wrapper"]//ul[contains(concat(" ", normalize-space(@class), " "), " ui-widget-content ")]//a[text() = "' . $value . '"]';
    $this->waitForElementVisible('xpath', $xpath)->click();
  }

  /**
   * Retrieves the messages container element from the page.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The messages container element.
   *
   * @throws \Exception
   *   Thrown when the messages container is not found on the page.
   */
  private function getMessagesContainer(): NodeElement {
    $page = $this->getSession()->getPage();
    $messagesContainer = $page->find('css', '.private-message-thread-messages .field__items');

    if (NULL === $messagesContainer) {
      throw new \Exception('There are currently no messages available.');
    }

    return $messagesContainer;
  }

}
