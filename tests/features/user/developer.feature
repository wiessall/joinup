@api @group-f
Feature: Variety tests for the developer role.

  Scenario: Only a developer sees the warning message.
    Given I am an anonymous user
    And I am on the homepage
    Then I should not see the text "Warning: You are working on production. Configuration is unlocked. Please, be extra cautious!"

    # Developers see the warning both in the front theme and the admin theme.
    Given I am logged in as a developer
    And I am on the homepage
    Then I should see the warning message "Warning: You are working on production. Configuration is unlocked. Please, be extra cautious!"
    When I go to "/admin/content"
    Then I should see the warning message "Warning: You are working on production. Configuration is unlocked. Please, be extra cautious!"

    # An authenticated user cannot see the message.
    Given I am logged in as an authenticated
    And I am on the homepage
    Then I should not see the text "Warning: You are working on production. Configuration is unlocked. Please, be extra cautious!"
