@api @group-f
Feature: Private message report.
  In order to report private message and ban user
  As a user
  I need to be able to report a message.

  @javascript
  Scenario: Report a private message
    Given users:
      | Username  | First name | Family name | E-mail                | Roles     |
      | alice_m   | Alice      | Miller      | alice.m@example.com   | Moderator |
      | bob_rolls | Bob        | Rolls       | bob.rolls@example.com |           |

    Given I am logged in as "alice_m"
    And I am on "/user/messages"
    And I click "New message"
    Then a modal should open
    When I type "Bob" in the "To:" autocomplete field
    And I wait for AJAX to finish
    Then I should see the text "Bob Rolls" in the "Private message autocomplete results" region
    When I pick "Bob Rolls" from the "Member" autocomplete suggestions
    And I enter "This is a mean message to Bob." in the "Message" wysiwyg editor
    And I press "Send" in the "Modal buttons" region
    Then the modal should be closed
    And a message containing the text "This is a mean message to Bob." should be present
    And I should not see the link "Report message"

    When I am logged in as "bob_rolls"
    And I am on "/user/messages"
    And a message containing the text "This is a mean message to Bob." should be present
    When I click "Report message"
    Then a modal should open
    And I should see the text "Report and block sender" in the "Modal title"
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
    And I click "New message"
    Then a modal should open
    When I type "Bob" in the "To:" autocomplete field
    And I wait for AJAX to finish
    Then I should not see the text "Bob Rolls" in the "Private message autocomplete results" region

    Given I am logged in as "bob_rolls"
    When I am on "/user/messages/settings"
    And I click "Unblock"
    Then a modal should open
    And I should see the text "Are you sure you want to unblock user Alice Miller?" in the "Modal title"
    And I should see the button "Confirm"
    When I press "Confirm" in the "Modal buttons" region
    Then the modal should be closed
    And a message containing the text "This is a mean message to Bob." should be present
