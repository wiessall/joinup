@api @group-f
Feature: Private message report.
  In order to report private message and block an user
  As a user
  I need to be able to report a message.

  Background:
    Given the following users:
      | ID   | Username  | First name | Family name | E-mail                | Roles     |
      | 900  | alice_m   | Alice      | Miller      | alice.m@example.com   | Moderator |
      | 9001 | bob_rolls | Bob        | Rolls       | bob.rolls@example.com |           |

  @javascript
  Scenario: Report a private message
    Given I am logged in as "alice_m"
    And I am on "/user/messages"
    And I click "New message"
    Then a modal should open
    When I type "Bob" in the "To:" autocomplete field
    Then I should see the text "Bob Rolls" in the "Private message autocomplete results" region
    When I pick "Bob Rolls" from the "Member" autocomplete suggestions
    And I fill in "Message" with "This is a mean message to Bob."
    And I press "Send" in the "Modal buttons" region
    Then the modal should be closed
    And a message containing the text "This is a mean message to Bob." should be present
    And I should not see the link "Report message"

    When I am logged in as "bob_rolls"
    And I am on "/user/messages"
    And a message containing the text "This is a mean message to Bob." should be present
    And I should see a ".private_message_report[title='Report abusive content']" element
    When I click "Report message"
    Then a modal should open
    And I should see the text "Report and block sender" in the "Modal title"
    And I press "Send" in the "Modal content" region
    Then I should see the text "Report field is required."
    When I fill in "Report" with "I want to report this message."
    And I check "Also block this sender"
    And I press "Send" in the "Modal content" region
    Then the modal should be closed
    And I should see the success message "Thank you, your report was sent to the moderator team for further investigation."
    And I should see the message "You will not receive anymore messages from Alice Miller."
    And a message containing the text "This is a mean message to Bob." should not be present
    And I should not see the link "Report"
    And the email sent to "alice.m@example.com" with subject "User Bob Rolls reported a message" contains the text:
      | The user Bob Rolls has reported user Alice Miller for sending an unwanted message (view message): |
      | Message content:                                                                                  |
      | This is a mean message to Bob.                                                                    |
      | Report reason:                                                                                    |
      | I want to report this message.                                                                    |

    # A blocked user should not be able to send messages to the user that
    # blocked them.
    When I am logged in as "alice_m"
    And I am on "/user/messages"
    # Since there is only the thread with bob_rolls, we can already assert that
    # the wysiwyg editor and the "Send" button are not present.
    And the following button should be disabled "Send"
    And I click "New message"
    Then a modal should open
    When I type "Bob" in the "To:" autocomplete field
    Then I should not see the text "Bob Rolls" in the "Private message autocomplete results" region

    # Ensure that the user cannot force the user to the recipient list.
    When I type "Bob Rolls (9001)" in the "To:" autocomplete field
    And I fill in "Message" with "This is another mean message to Bob."
    And I press "Send" in the "Modal buttons" region
    Then I should see the error message "You cannot send messages to a user that has blocked you, or was blocked by you."

    Given I am logged in as "bob_rolls"
    When I am on "/user/messages/settings"
    And I click "Unblock"
    Then a modal should open
    And I should see the text "Are you sure you want to unblock user Alice Miller?" in the "Modal title"
    And I should see the button "Confirm"
    When I press "Confirm" in the "Modal buttons" region
    Then the modal should be closed
    And a message containing the text "This is a mean message to Bob." should be present

  @javascript
  Scenario: Validation works for blocked users.
    Given the following users:
      | Username  | First name | Family name | E-mail                |
      | joan_tiff | Joan       | Tiff        | joan.tiff@example.com |
    And the following private messages:
      | owner     | recipient | message |
      | alice_m   | bob_rolls | Hello 1 |
      | joan_tiff | bob_rolls | Hello 2 |
    When I am logged in as "bob_rolls"
    And I am on "/user/messages"
    And I click "Alice Miller" in thread message card
    And I fill in "Reply" with "A private message from Bob to Alice."

    # Simulate that Alice has blocked Bob while bob has already filled the
    # message form and is about to send the message.
    And "alice_m" has blocked "bob_rolls"
    And I press "Send"
    Then I should see the warning message "You cannot send messages to a user that has blocked you, or was blocked by you."
    And I reload the page
    And I should not see the text "A private message from Bob to Alice."

    # Bob can send messages to other user that is not blocked.
    When I click "Joan Tiff" in thread message card
    Then I should not see the warning message "You cannot send messages to a user that has blocked you, or was blocked by you."
