@api @group-clone
Feature: Ensure contacts were migrated and exist.

  Scenario: Smoke test for the contact information.
    Given I go to "/collection/joinup"
    Then I should see the link "Joinup Support Team"
    And I should see the text "Owner"
    And I should see the text "European Commission"
    And I should see the text "Supra-national authority"
