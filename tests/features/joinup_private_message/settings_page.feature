@api @group-f
Feature: Private message settings page management.
  In order to remove private message bans and allow email notifications
  As a user
  I need to be able to manage private message settings page

  @javascript
  Scenario: Testing setting page
    Given the following users:
      | Username     | E-mail           | First name | Family name |
      | Lisa_Like    | lisa@like.com    | Lisa       | Like        |
      | Gregory_Like | gregory@like.com | Gregory    | Like        |
      | Michael_Like | michael@like.com | Michael    | Like        |
    Given the following private message bans:
      | owner        | target       | created    |
      | Gregory_Like | Lisa_Like    | 2024-09-20 |
      | Gregory_Like | Michael_Like | 2024-09-21 |

    # Test anonymous access.
    Given I am an anonymous user
    When I go to "/user/messages/settings"
    Then I should see the heading "Sign in to continue"

    # Test the settings page.
    Given I am logged in as "Lisa_Like"
    When I go to "/user/messages/settings"
    Then I should see the heading "Private Messages"
    And I should see the text " There are no blocked users."

    # Test changing the settings.
    And the "Receive email notification of private messages" checkbox should be checked
    When I uncheck "Receive email notification of private messages"
    And I press "Save"
    Then I should see the success message "Private message settings were stored"
    And the "Receive email notification of private messages" checkbox should be unchecked

    # Test cache is not liking between users.
    Given I am logged in as "Gregory_Like"
    When I go to "/user/messages/settings"
    Then the "Receive email notification of private messages" checkbox should be checked

    # Test unblocking another user.
    When I click "Unblock" in the "Lisa Like" row
    Then a modal should open
    And I should see the text "Are you sure you want to unblock user Lisa Like?" in the "Modal title"
    When I press "Confirm" in the "Modal buttons" region
    Then I should be on "/user/messages"

    # Test the task menu entries.
    When I go to "/user/messages/settings"
    And I open the header local tasks menu
    And I click "Messages"
    Then I should be on "/user/messages"
    When I open the header local tasks menu
    And I click "Settings"
    Then I should be on "/user/messages/settings"
