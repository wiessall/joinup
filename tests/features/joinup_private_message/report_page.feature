@api @group-f
Feature: Private message report page
  In order to flag as ok or delete reported private message
  As a user
  I need to be able to see report page

  Background:
    Given users:
      | Username  | First name | Family name | E-mail                | Roles     |
      | alice_m   | Alice      | Miller      | alice.m@example.com   | developer |
      | bob_rolls | Bob        | Rolls       | bob.rolls@example.com |           |
      | joan_tiff | Joan       | Tiff        | joan.tiff@example.com |           |

    Given the following private messages:
      | owner     | recipient | message                    | report | report_timestamp |
      | alice_m   | bob_rolls | This is message from alice |        |                  |
      | joan_tiff | bob_rolls | This is message from joan  | test   | 1729593851       |

  @javascript
  Scenario: Display, unreport and delete reported private messages
    When I am logged in as "bob_rolls"
    And I am on the homepage
    And I should see 2 unread private message
    And I am on "/user/messages"
    And I should see the heading "Private Messages"
    And I should see the link "Report"
    When I click "Report" link in message "This is message from alice"
    And a modal should open
    When I fill in "Report" with "I want to report this message"
    And I press "Send" in the "Modal content" region
    Then the modal should be closed

    When I am logged in as "alice_m"
    And I am on "/dashboard/private-message-report"
    Then I should see "This is message from alice"
    And I should see "Flag as OK"

    When I am logged in as "bob_rolls"
    And I am on "/dashboard/private-message-report"
    Then I should see "Access denied"

    When I am logged in as "alice_m"
    And I am on "/dashboard/private-message-report"
    Then I should see "This is message from joan"
    And I should see "Flag as OK"
    When I click "Flag as OK" in the "This is message from joan" row
    And a modal should open
    Then I should see "Flag message as OK?"
    And I press "Confirm" in the "Modal buttons" region
    Then the modal should be closed
    Then I am on "/dashboard/private-message-report"
    And  I should not see "This is message from joan"
    And I should see "This is message from alice"
    When I press the button "Toggle Dropdown" in the "This is message from alice" row
    Then I click "Delete" in the "This is message from alice" row
    And a modal should open
    And I should see "Are you sure you want to delete the private"
    And I press "Delete" in the "Modal buttons" region
    Then the modal should be closed
    And  I should not see "This is message from alice"
    When I go to "/user/messages"
    Then  I should not see "This is message from alice"
