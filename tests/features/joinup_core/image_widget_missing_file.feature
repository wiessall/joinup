@api @group-a
Feature: Editing a collection with a missing logo file does not cause a fatal error.

  Scenario: Submitting a collection edit form with a missing logo file shows a validation error, not a crash.
    Given collection content:
      | title             | state     |
      | Broken Collection | published |
    And the "Broken Collection" collection has a logo with a missing physical file
    Given I am logged in as a moderator
    When I go to the edit form of the "Broken Collection" collection
    And I press "Publish"

    Then the response status code should be 200
    And I should not see the text "TypeError"
