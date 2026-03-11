@api @group-clone
Feature: ISAICP-11024.

  @update:collection_deploy_201000
  Scenario: Collection edit blocked by stale pending revisions
    When I am logged in as a moderator
    And I go to the edit form of the "EU LDT Toolbox" collection
    And I fill in "Alternative text" with "Alternative logo text"
    And I press "Save as draft"
    Then I should see the success message "Collection EU LDT Toolbox has been updated."
