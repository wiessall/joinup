@api @group-c
Feature: Assessment count on solution home page

  Scenario: Assessment count in solution header
    Given the assessments collection exists
    And the following "solution" content:
      | title           |
      | Linked Solution |
    When I go to the homepage of the "Linked Solution" solution
    Then I should not see the text "Assessment"

    # One report with two binding requirements still counts as one.
    Given report assessments:
      | name     | state     |
      | Report A | published |
    And the following report has binding requirement:
      | report | Report A        |
      | asset  | Linked Solution |
    And the following report has binding requirement:
      | report | Report A        |
      | asset  | Linked Solution |
    When I go to the homepage of the "Linked Solution" solution
    Then I should see the text "1 Assessment"

    # A second published report increments the count.
    Given report assessments:
      | name     | state     |
      | Report B | published |
    And the following report has binding requirement:
      | report | Report B        |
      | asset  | Linked Solution |
    When I go to the homepage of the "Linked Solution" solution
    Then I should see the text "2 Assessments"

    # Unpublished reports are not counted.
    Given report assessments:
      | name        | state |
      | Unpublished | draft |
    And the following report has binding requirement:
      | report | Unpublished     |
      | asset  | Linked Solution |
    When I go to the homepage of the "Linked Solution" solution
    Then I should see the text "2 Assessments"
