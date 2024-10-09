<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;

/**
 * Behat step definitions for testing private messages.
 */
class PrivateMessageContext extends RawDrupalContext {

  use JavascriptTrait;
  use TestingEntitiesTrait;

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
   * Checks that multiple texts are present within private thread messages.
   *
   * Example table format:
   * | Message one     |
   * | Thread 2 reply |
   * | Another thread |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table node containing the texts to be checked.
   *   Each row should contain one text string.
   *
   * @throws \Exception
   *   Thrown when any of the specified texts are not
   *   found in the private messages thread.
   *
   * @Then I should see the following threads in private messages:
   */
  public function iShouldSeeTheFollowingThreadInPrivateMessages(TableNode $table): void {
    $messagesContainer = $this->getThreadsContainer();

    $messageText = $messagesContainer->getText();
    $texts = $table->getColumn(0);

    foreach ($texts as $text) {
      if (strpos($messageText, $text) === FALSE) {
        throw new \Exception(sprintf('The text "%s" was not found in any private message thread.', $text));
      }
    }
  }

  /**
   * Clicks on a private message thread card with the specified subject.
   *
   * @param string $subject
   *   The subject of the private message thread to click on.
   *
   * @throws \Exception
   *   Thrown if the thread with the specified subject is not found or if there
   *   are no thread messages available.
   *
   * @Then I click :subject in thread message card
   */
  public function clickPrivateMessageThread(string $subject): void {
    $messagesContainer = $this->getThreadsContainer();
    $xpath = '//div[contains(concat(" ", normalize-space(@class), " "), " private-message-thread__subject ")]//div[text() = "' . $subject . '"]';
    $threadCard = $messagesContainer->find('xpath', $xpath);
    if (NULL === $threadCard) {
      throw new \Exception('There are currently no thread messages available.');
    }
    $threadCard->getParent()->getParent()->find('css', '.private-message-inbox-thread-link')->click();
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

  /**
   * Retrieves the messages thread container element from the page.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The messages thread container element.
   *
   * @throws \Exception
   *   Thrown when the messages thread container is not found on the page.
   */
  private function getThreadsContainer(): NodeElement {
    $page = $this->getSession()->getPage();
    $messagesContainer = $page->find('css', '.private-message-thread--full-container');

    if (NULL === $messagesContainer) {
      throw new \Exception('There are currently no messages thread available.');
    }

    return $messagesContainer;
  }

}
