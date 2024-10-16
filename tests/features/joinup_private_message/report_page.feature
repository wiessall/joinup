@api @group-f
Feature: Private message report page
  In order to flag as ok or delete reported private message
  As a user
  I need to be able to see report page

  @javascript
  Scenario: Send a private message
    Given users:
      | Username  | First name | Family name | E-mail                | Roles              |
      | alice_m   | Alice      | Miller      | alice.m@example.com   | developer          |
      | bob_rolls | Bob        | Rolls       | bob.rolls@example.com |                    |
      | joan_tiff | Joan       | Tiff        | joan.tiff@example.com |                    |

    Given I am logged in as "alice_m"
    And I am on the homepage
    And I should see 0 unread private message
    And I am on "/user/messages"
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
    And I am on "/user/messages"
    And I should see the heading "Private Messages"
    And I should see the link "Report"
    Then I click "Report" in the "Report button wrapper" region
    And a modal should open
    When I enter "I want to report this message" in the "Report" textarea
    And I press "Send" in the "Modal content" region

    When I am logged in as "alice_m"
    And I am on "/dashboard/private-message-report"
    Then I should see "This is a private message to Bob."
    And I click "Flag as OK"
    Then I should be on "/dashboard/private-message-report"

    When I am logged in as "bob_rolls"
    And I am on "/dashboard/private-message-report"
    Then I should see "Access denied"
