@api @group-f
Feature: Private message settings page management.
  In order to remove private message bans and allow email notifications
  As a user
  I need to be able to manage private message settings page

  @javascript
  Scenario: Testing setting page
    Given users:
      | Username     | Roles | E-mail           | First name | Family name |
      | Lisa Like    |       | lisa@like.com    | Lisa       | Like        |
      | Gregory Like |       | gregory@like.com | Gregory    | Like        |
      | Michael Like |       | michael@like.com | Michael    | Like        |
    Given the following private message bans:
      | owner        | target       | created    |
      | Gregory Like | Lisa Like    | 2024-09-20 |
      | Gregory Like | Michael Like | 2024-09-21 |

    Given I am an anonymous user
    When I go to "/user/messages/settings"
    Then I should see the heading "Sign in to continue"

    Given I am logged in as "Gregory Like"
    When I go to "/user/messages/settings"
    Then I should see the heading "Private Messages"
    And I check "Receive email notification of private messages"
    And I press "Save configuration"
    Then I should see the success message "Private message settings were stored"
    And I should see a table

    When I click "Unblock" in the "Lisa Like" row
    Then a modal should open
    And I should see the text "Are you sure you want to unblock user Lisa Like?" in the "Modal title"
    When I press "Confirm" in the "Modal buttons" region
    Then I should be on "/user/messages"

    When I go to "/user/messages/settings"
    And I open the header local tasks menu
    And I click "Messages"
    Then I should be on "/user/messages"
    When I open the header local tasks menu
    And I click "Settings"
    Then I should be on "/user/messages/settings"
