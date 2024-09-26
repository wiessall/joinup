@api @group-f
Feature: Private messages
  As an authenticated user
  I want to send a private message to another user
  So that I can communicate with them privately

  Background:
    Given users:
      | Username  | E-mail                |
      | Alice M   | alice.m@example.com   |
      | Bob Rolls | bob.rolls@example.com |
      | Joan Tiff | joan.tiff@example.com |

  @javascript
  Scenario: Send a private message
    Given I am logged in as "Alice M"
    And I am on the homepage
    And I should see 0 unread private message
    And I am on "/user/messages/message/create"
    When I type "Bob Rolls" in the "To" autocomplete field
    And I wait until the page contains the text "Bob Rolls"
    And I pick "Bob Rolls" from the private message user autocomplete suggestions
    And I fill in "Subject" with "Hello Bob"
    And I enter "This is a private message to Bob." in the "Message" wysiwyg editor
    And I press "Send"
    Then I should see the heading "Private Messages"
    And I should see 1 private message
    Then I should see a private message containing the text "This is a private message to Bob."

    When I am logged in as "Bob Rolls"
    And I am on the homepage
    And I should see 1 unread private message
    Then I open the account menu
    And I click "My messages"
    And I should see the heading "Private Messages"
    And I should see the link "New Message"
    And I should not see the text "You do not have any messages"
    And I should see 1 private message
    Then I should see a private message containing the text "This is a private message to Bob."
    And I enter "This is a private message to Alice." in the "Reply" wysiwyg editor
    And I press "Send"
    And I should see 2 private message
    Then I should see the following texts in private messages:
      | This is a private message to Bob.   |
      | This is a private message to Alice. |
    And I click "New Message"
    When I type "Joan Tiff" in the "To" autocomplete field
    And I wait until the page contains the text "Joan Tiff"
    And I pick "Joan Tiff" from the private message user autocomplete suggestions
    And I fill in "Subject" with "Hello Joan"
    And I enter "This is a private message to Joan." in the "Message" wysiwyg editor
    And I press "Send"
    Then I should see the following threads in private messages:
      | Hello Joan |
      | Hello Bob  |
    Then I click "Hello Joan" in thread message card
    And I should see 1 private message
    Then I should see the following texts in private messages:
      | This is a private message to Joan. |
    Then I click "Hello Bob" in thread message card
    And I wait until the page contains the text "This is a private message to Bob."
    And I should see 2 private message
    Then I should see the following texts in private messages:
      | This is a private message to Bob.   |
      | This is a private message to Alice. |

  Scenario: Check a user without messages
    Given I am logged in as "Alice M"
    And I am on the homepage
    And I should see 0 unread private message
    When I click "My messages" in the "Navigation bar" region
    Then I should see the heading "Private Messages"
    And I should see the link "New Message"
    And I should see 0 private message
    And I should see the text "You do not have any messages"
