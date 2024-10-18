<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\private_message\Entity\PrivateMessageBan;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Behat step definitions for testing private messages.
 */
class PrivateMessageContext extends RawDrupalContext {

  use JavascriptTrait;
  use TestingEntitiesTrait;
  use UserTrait;

  /**
   * Creates a set of private messages.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The private messages data.
   *
   *   Table format:
   *
   * @code
   *   | owner   | message | recipient |
   *   | alice_m | hello   | bob_rolls |
   * @endcode
   *
   * @Given the following private messages:
   */
  public function createPrivateMessages(TableNode $table): void {
    $storage = \Drupal::entityTypeManager()->getStorage('private_message');

    foreach ($table->getColumnsHash() as $values) {
      foreach (['owner', 'message', 'recipient'] as $required) {
        if (!isset($values[$required])) {
          throw new \Exception("Missing column $required");
        }
      }

      $owner = user_load_by_name($values['owner']);
      if (!$owner instanceof AccountInterface) {
        throw new \Exception("Owner {$values['owner']} could not be found.");
      }

      $privateMessageThread = $this->getThread($values['owner'], $values['recipient']);
      if ($privateMessageThread->get('subject')->isEmpty()) {
        $privateMessageThread->set('subject', $values['message'])->save();
      }

      /** @var \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage */
      $privateMessage = $storage->create([
        'owner' => $owner->id(),
        'message' => [
          'value' => $values['message'],
          'format' => 'minimal',
        ],
      ]);
      $privateMessage->save();

      $privateMessageThread->addMessage($privateMessage)->save();

      $this->entities['private_message'][$privateMessage->id()] = $privateMessage;
      $this->entities['private_message_thread'][$privateMessageThread->id()] = $privateMessageThread;
    }
  }

  /**
   * Navigates to the canonical page display of a thread.
   *
   * @param string $owner
   *   Owner username.
   * @param string $recipient
   *   Recipient username.
   *
   * @When I visit the :owner :recipient thread
   */
  public function visitThread(string $owner, string $recipient): void {
    $entity = $this->getThread($owner, $recipient);
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Gets private message thread.
   *
   * @param string $owner
   *   Owner username.
   * @param string $recipient
   *   Recipient username.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThreadInterface
   *   Private message thread.
   */
  public function getThread(string $owner, string $recipient): PrivateMessageThreadInterface {
    /** @var \Drupal\private_message\Service\PrivateMessageServiceInterface $service */
    $service = \Drupal::service('private_message.service');

    $ownerEntity = user_load_by_name($owner);
    if (!$ownerEntity instanceof AccountInterface) {
      throw new \Exception("Owner {$owner} could not be found.");
    }

    $recipientEntity = user_load_by_name($recipient);
    if (!$recipientEntity instanceof AccountInterface) {
      throw new \Exception("Recipient {$recipient} could not be found.");
    }

    return $service->getThreadForMembers([$ownerEntity, $recipientEntity]);
  }

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
    $messagesContainer = $page->find('css', '.private-message-thread-messages');

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
      if (!str_contains($messageText, $text)) {
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
    $xpath = '//div[contains(concat(" ", normalize-space(@class), " "), " private-message-inbox ")]//div[text() = "' . $subject . '"]';
    $threadCard = $messagesContainer->find('xpath', $xpath);
    if (NULL === $threadCard) {
      throw new \Exception('There are currently no thread messages available.');
    }
    $threadCard->getParent()->getParent()->getParent()
      ->find('css', '.private-message-inbox-thread-link')->click();
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
   * Sets the limit for messages/threads allowed.
   *
   * @param int|null $limit
   *   The limit. If not passed, the original value is restored.
   *
   * @Given the limit for messages or threads allowed is :limit
   * @Then I restore the limit for messages or threads allowed
   */
  public function setPrivateMessageLimit(?int $limit = NULL): void {
    static $original_value;

    $config = \Drupal::configFactory()
      ->getEditable('private_message_flood.role.authenticated');
    $current_value = $config->get('limit');
    if (!isset($original_value)) {
      $original_value = $current_value;
    }

    $limit = $limit ?: $original_value;
    if ($current_value !== $limit) {
      $config->set('limit', $limit)->save();
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
    $messagesContainer = $page->find('css', '.private-message-thread-messages');

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

  /**
   * Create private message bans.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The collection data.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given the following private message bans:
   */
  public function givenPrivateMessageBans(TableNode $table): void {
    foreach ($table->getHash() as $row) {
      $ban = PrivateMessageBan::create([
        'owner' => $this->getUserByName($row['owner'])->id(),
        'target' => $this->getUserByName($row['target'])->id(),
        'created' => strtotime($row['created']),
      ]);
      $ban->save();
    }
  }

  /**
   * Opens url to unblock user with private message ban param.
   *
   * @When I go to the unblock page to unblock user :target banned by :owner
   */
  public function iGoToTheUnblockPage(string $target, string $owner): void {
    $banId = $this->getPrivateMessageBanIdByUserNames($target, $owner);
    $url = Url::fromRoute('joinup_private_message.unblock', ['private_message_ban' => $banId])->toString();
    $this->visitPath($url);
  }

  /**
   * Returns ban Id by owner and target names.
   *
   * @param string $targetName
   *   Ban target.
   * @param string $ownerName
   *   Ban owner.
   *
   * @return string
   *   Ban id.
   *
   * @throws \Exception
   *   If there is no ban, throws exception.
   */
  public function getPrivateMessageBanIdByUserNames(string $targetName, string $ownerName): string {
    $ownerId = $this->getUserByName($ownerName)->id();
    $targetId = $this->getUserByName($targetName)->id();
    $query = \Drupal::entityQuery('private_message_ban')
      ->accessCheck(FALSE)
      ->condition('owner', $ownerId)
      ->condition('target', $targetId)
      ->range(0, 1);
    $result = $query->execute();
    if (empty($result)) {
      throw new \Exception('There is no message ban for target ' . $targetName . ' amd owner ' . $ownerName);
    }
    $ban = reset($result);

    return $ban;
  }

}
