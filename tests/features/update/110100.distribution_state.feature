@api @clone
Feature:
  Test update 110100.

  Scenario: Distributions were updated successfully.
    Given I am logged in as a moderator
    And I go to the edit form of the "Joinup source code v.1.100.0" distribution
    Then I should see the text "Current workflow state"
    And I should see the text "Published"

