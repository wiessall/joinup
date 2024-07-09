@api @group-clone
Feature: Test update hook.

  @update:joinup_core_update_111002()
  Scenario: Check the add news path.
    Given I am logged in as a moderator
    When I go to "/collection/interoperability-test-bed-repository/solution/interoperability-test-bed/news/add"
    Then the response status code should be 200
