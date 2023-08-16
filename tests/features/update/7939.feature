@api @group-clone
Feature: Test ISAICP-7939.

  Scenario: Regression test that the meta entity is also OK.
    When I am logged in as a moderator
    And I go to "/admin/homepage-topics"
    And I click "Digital innovation"
    Then I should see the heading "Digital innovation"
