@api @group-f @javascript
Feature: Private messages
  As an authenticated user
  I want to send a private message to another user
  So that I can communicate with them privately

  Background:
    Given users:
      | Username  | E-mail                |
      | Alice M   | alice.m@example.com   |
      | Bob Rolls | bob.rolls@example.com |
      | Rui Mint  | rui.mint@example.com  |

  Scenario: Send a private message
    Given I am logged in as "Alice M"
    And I am on the homepage
    And I should see 0 unread private message
    And I am on "/user/messages/message/create"
    When I type "Bob Rolls" in the "To" autocomplete field
    And I wait until the page contains the text "Bob Rolls"
    And I pick "Bob Rolls" from the private message user autocomplete suggestions
    And I fill in "Subject" with "Hello Bob"
    And I fill in "Message" with "Lorem Ipsum is simply dummy text of the printing and typesetting industry."
    And I press "Send"
    And I should see "Your message has been sent."

    When I am logged in as "Bob Rolls"
    And I am on the homepage
    And I should see 1 unread private message
    And I click "My messages"
    Then I should see the heading "Private Messages"
    And I should not see the text "You do not have any messages"
    And I should see 1 private message
    Then I should see a private message containing the text "This is a private message to Bob."
    And I fill in "Message" with "This is a private message to Alice."
    And I press "Send"
    And I should see 2 private message
    Then I should see the following texts in private messages:
      | This is a private message to Bob.   |
      | This is a private message to Alice. |

    # Check a user without messages.
    Given I am logged in as "Rui Mint"
    And I am on the homepage
    And I should see 0 unread private message
    When I click "My messages"
    Then I should see the heading "Private Messages"
    And I should see 0 private message
    And I should see the text "You do not have any messages"
