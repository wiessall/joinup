@api @group-e
Feature: Tests related to searching assessments and sub entities.

  Scenario: Solutions tied to assessments are not searchable.
    Given solution content:
      | title                                   | state     | collection                           |
      | some random text to search architecture | published | Solutions mentioned in an assessment |
    When I visit the advanced search page
    And I enter "architecture" in the search bar and press enter
    Then I should not see the "some random text to search architecture" tile
