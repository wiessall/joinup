@api @group-clone
Feature: Tests related to topic meta entities

  Scenario: Regression test for orphaned topic meta entities on admin page
    Given I am logged in as a moderator
    And I visit "/admin/homepage-topics"
    Then the response status code should be 200
    And I should see the heading "Topics"

  Scenario: Regression test for 403 page title not shown to authenticated user
    Given I am logged in as a authenticated
    And I check the box "I agree to the Legal notice document"
    And I press "Submit"
    When I go to the canonical path of the "collection_settings" meta entity of the "Portal support" collection_settings "content"
    Then I should get an access denied error
    And I should see the heading "Access denied"
