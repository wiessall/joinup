@api
Feature: Tests related to topic meta entities

  @group-clone
  Scenario: Regression test for orphaned topic meta entities on admin page
    Given I am logged in as a moderator
    And I visit "/admin/homepage-topics"
    Then the response status code should be 200
    And I should see the heading "Topics"
