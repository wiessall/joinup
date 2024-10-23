@api @group-f
Feature: Private message report.
  In order to report private message and ban user
  As a user
  I need to be able to report a message.

  @javascript
  Scenario: Send a private message
    Given users:
      | Username  | First name | Family name | E-mail                | Roles     |
      | alice_m   | Alice      | Miller      | alice.m@example.com   |           |
      | bob_rolls | Bob        | Rolls       | bob.rolls@example.com |           |
      | joan_tiff | Joan       | Tiff        | joan.tiff@example.com | Moderator |

    Given I am logged in as "alice_m"
    And I am on "/user/messages"
    And I click "New message"
    Then a modal should open
    When I type "Bob Rolls" in the "To:" autocomplete field
    And I wait for AJAX to finish
    And I pick "Bob Rolls" from the "Member" autocomplete suggestions
    And I enter "This is a mean message to Bob." in the "Message" wysiwyg editor
    And I press "Send" in the "Modal buttons" region
    Then the modal should be closed
    And I should see a private message containing the text "This is a mean message to Bob."
    And I should not see the link "Report"

    When I am logged in as "bob_rolls"
    And I am on "/user/messages"
    When I click "Report"
    Then a modal should open
    And I should see the text "Report and block sender" in the "Modal title"
    When I fill in "Report" with "I want to report this message."
    And I check "Also block this sender"
    And I press "Send" in the "Modal content" region
    Then I should see the message "Your report has been sent: I want to report this message."
    And I should see the message "The user alice_m has been banned."
    And I should not see the link "Report"
    And the email sent to "joan.tiff@example.com" with subject "User Bob Rolls reported a message" contains the text:
      | The user Bob Rolls has reported user Alice Miller for sending an unwanted message (view message): |
      | This is a mean message to Bob.
