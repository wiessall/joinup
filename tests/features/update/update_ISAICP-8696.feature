@api @group-clone
Feature: Error when editing a release.

  Scenario: Regression test for ISAICP-8696

    Given I am logged in as a moderator
    When I visit "/collection/joinup/solution/joinup-source-code"
    And I click "Add release"
    And I fill in "Name" with "JoinupX"
    And I fill in "Release version" with "1.105.0"
    When I press "Publish"
    Then I should see "JoinupX 1.105.0"

    When I click "Edit"
    And I select "Completed" from "Status"
    When I press "Update"
    Then I should see "JoinupX 1.105.0"

    But I delete the "JoinupX" release
