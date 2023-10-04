@api @group-clone
Feature: Tests for the 1.98 release.

  Scenario: ISAICP-8131 - fix contact information states.
    Given I am logged in as a moderator
    And I go to the edit form of the "Joinup" collection
    Then I should see the button "Edit" in the "Contact information inline form" region
    And I should see the button "Remove" in the "Contact information inline form" region
