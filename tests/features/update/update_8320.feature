@api @group-clone
Feature: Post update tests for ISAICP-8320.

  Scenario: People does not generate a fatal error.
    Given I am logged in as a moderator

    Then I visit "admin/people"

    Then I click "Last access"
    When I should see the heading "People"

    Then I click "Last access"
    When I should see the heading "People"
