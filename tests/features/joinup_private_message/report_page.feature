@api @group-f
Feature: Private message report page
  In order to flag as ok or delete reported private message
  As a user
  I need to be able to see report page

  Background:
    Given the following users:
      | Username   | First name | Family name | E-mail                 | Roles     |
      | alice_m    | Alice      | Miller      | alice.m@example.com    | moderator |
      | bob_rolls  | Bob        | Rolls       | bob.rolls@example.com  |           |
      | ken_carson | Ken        | Carson      | ken_carson@example.com |           |

    Given the following private messages:
      | owner      | recipient | message                  | report             | report_timestamp |
      | bob_rolls  | alice_m   | This is message from bob | reported by alice1 | 1729593851       |
      | ken_carson | alice_m   | This is message from ken | reported by alice2 | 1729593851       |

  @javascript
  Scenario: Display, unreport and delete reported private messages
    When I am logged in as "alice_m"
    And I am on "/dashboard"
    Then I should see the text "Private messages reports"
    When I click "Private messages reports"
    Then I should see the following lines of text:
      | This is message from bob |
      | This is message from ken |
    And I should see the link "Flag as OK"

    When I go to admin page of reported private message with text "This is message from bob"
    Then I should see the following lines of text:
      | This is message from bob |
      | reported by alice1       |
    And I should see the link "Flag as OK"

    # "Flag as OK" functionality.
    When I am on "/dashboard/private-message-report"
    And I click "Flag as OK" in the "This is message from bob" row
    Then a modal should open
    And I should see the text "Flag message as OK?"
    And I press "Confirm" in the "Modal buttons" region
    Then the modal should be closed
    When I am on "/dashboard/private-message-report"
    Then I should not see the text "This is message from bob"

    # "Delete" functionality
    When I am on "/dashboard/private-message-report"
    And I press the button "Toggle Dropdown" in the "This is message from ken" row
    Then I click "Delete" in the "This is message from ken" row
    And a modal should open
    And I should see the text "Are you sure you want to delete the private"
    And I press "Delete" in the "Modal buttons" region
    And the modal should be closed
    And I should not see the text "This is message from ken"
    When I go to "/user/messages"
    Then I should not see the text "This is message from ken"
