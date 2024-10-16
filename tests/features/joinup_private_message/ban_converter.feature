@api @group-f
Feature: Access check for unblock user path
  In order to remove private message ban I created
  As a user
  I need to be owner of this private message ban

  Scenario: Testing setting page
    Given users:
      | Username     | E-mail           | First name | Family name |
      | Lisa_Like    | lisa@like.com    | Lisa       | Like        |
      | Gregory_Like | gregory@like.com | Gregory    | Like        |
      | Michael_Like | michael@like.com | Michael    | Like        |
    Given the following private message bans:
      | owner        | target       | created    |
      | Gregory_Like | Lisa_Like    | 2024-09-20 |
      | Gregory_Like | Michael_Like | 2024-09-21 |

    Given I am logged in as "Gregory_Like"
    When I go to the unblock page to unblock user "Lisa_Like" banned by "Gregory_Like"
    Then I should see "Are you sure you want to unblock user"

    Given I am logged in as "Lisa_Like"
    When I go to the unblock page to unblock user "Lisa_Like" banned by "Gregory_Like"
    Then I should see "Page not found"

    Given I am logged in as "Michael_Like"
    When I go to the unblock page to unblock user "Lisa_Like" banned by "Gregory_Like"
    When I should see "Page not found"
