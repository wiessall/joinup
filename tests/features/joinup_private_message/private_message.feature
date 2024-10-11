@api @group-f
Feature: Private messages
  As an authenticated user
  I want to send a private message to another user
  So that I can communicate with them privately

  Background:
    Given users:
      | Username  | First name | Family name | E-mail                |
      | alice_m   | Alice      | Miller      | alice.m@example.com   |
      | bob_rolls | Bob        | Rolls       | bob.rolls@example.com |
      | joan_tiff | Joan       | Tiff        | joan.tiff@example.com |

  @javascript
  Scenario: Send a private message
    Given I am logged in as "alice_m"
    When I am on the homepage
    Then I should see 0 unread private message
    When I am on "/user/messages"
    And I click "New Message"
    Then a modal should open
    When I type "Bob Rolls" in the "Member(s) (value 1)" autocomplete field
    And I wait for AJAX to finish
    And I pick "Bob Rolls" from the "Member" autocomplete suggestions
    And I fill in "Subject" with "Hello Bob"
    And I enter "This is a private message to Bob." in the "Message" wysiwyg editor
    And I press "Send" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the heading "Private Messages"
    And I should see 1 private message
    Then I should see a private message containing the text "This is a private message to Bob."

    When I am logged in as "bob_rolls"
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
    When I click "New Message"
    Then a modal should open
    When I type "Joan Tiff" in the "Member(s) (value 1)" autocomplete field
    And I wait until the page contains the text "Joan Tiff"
    And I pick "Joan Tiff" from the "Member" autocomplete suggestions
    And I fill in "Subject" with "Hello Joan"
    And I enter "This is a private message to Joan." in the "Message" wysiwyg editor
    And I press "Send" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the following threads in private messages:
      | Hello Joan |
      | Hello Bob  |
    When I click "Hello Joan" in thread message card
    And I should see 1 private message
    Then I should see the following texts in private messages:
      | This is a private message to Joan. |
    Then I click "Hello Bob" in thread message card
    And I wait until the page contains the text "This is a private message to Bob."
    And I should see 2 private message
    Then I should see the following texts in private messages:
      | This is a private message to Bob.   |
      | This is a private message to Alice. |
    # Regression test for ISAICP-9130: second click impacts modal.
    When I click "Hello Bob" in thread message card
    And I wait for AJAX to finish
    And I click "New Message"
    Then a modal should open
    Then I close the modal dialog

    Given the limit for messages or threads allowed is 2
    When I click "New Message"
    Then I should see "Sorry, you have reached your limit for posting. Please try again later."
    When I am on "/user/messages"
    Then I should not see "Sorry, you have reached your limit for posting. Please try again later."
    When I enter "This is a fail." in the "Reply" wysiwyg editor
    And I press "Send"
    Then I should see "Sorry, you have reached your limit for posting. Please try again later."
    Then I restore the limit for messages or threads allowed

  Scenario: Check a user without messages
    Given I am logged in as "alice_m"
    And I am on the homepage
    And I should see 0 unread private message
    When I click "My messages" in the "Navigation bar" region
    Then I should see the heading "Private Messages"
    And I should see the link "New Message"
    And I should see 0 private message
    And I should see the text "You do not have any messages"

  Scenario: User can view a selected thread.
    Given the following private messages:
      | owner   | recipient | message |
      | alice_m | bob_rolls | Hello 2 |
      | alice_m | bob_rolls | Hello 1 |

    When I am logged in as "alice_m"
    And I visit the "alice_m" "bob_rolls" thread
    Then I should see the following texts in private messages:
      | Hello 1 |
      | Hello 2 |
