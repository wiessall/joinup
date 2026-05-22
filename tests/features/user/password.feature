@api @group-i
Feature: Password management
  As a registered user of the website
  I want to be able to manage and restore my password
  So that I can access my account

  Background:
    Given the following users:
      | Username       | E-mail                     | Password | First name | Family name |
      | Charlie Change | charlie.change@example.com | changeme | Charlie    | Change      |

  Scenario: A logged-in user can navigate to his profile but cannot change his password.
    When I am logged in as "Charlie Change"
    And I am on the homepage
    And I click "My account"
    And I click "Edit"
    Then the following fields should not be present "Current password,Password,Confirm password"
