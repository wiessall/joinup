@api @group-a
Feature: Assessment report search functionality
  Test search and filtering capabilities for assessment reports

  @javascript
  Scenario: Advanced search by content type and keyword
    Given public_organisation assessments:
      | name                  | organisation_type          | country |
      | Bluewave Technologies | european_union_institution | Belgium |
    And report assessments:
      | name                 | provider              | state     | created              |
      | Initial Overview     | Bluewave Technologies | published | 2020-01-01T00:00:00Z |
      | Transmission Summary |                       | published | 2020-01-02T00:00:00Z |
      | Acme Report          |                       | published | 2020-01-03T00:00:00Z |
      | Risk Evaluation      |                       | review    | 2020-01-04T00:00:00Z |
      | Draft Observation    |                       | draft     | 2020-01-05T00:00:00Z |
    And I am an anonymous user
    When I visit the advanced search page
    And I check the "Assessment reports (3)" checkbox from the "Content types" facet
    Then the page should show only the tiles "Initial Overview, Transmission Summary, Acme Report"

    When I enter "Transmission" in the search bar and press enter
    Then the page should show only the tiles "Transmission Summary"
    When I enter "Bluewave" in the search bar and press enter
    Then the page should show only the tiles "Initial Overview"

    When I visit the advanced search page
    Then I should not see the "Organisation country" facet
    And I check the "Assessment reports (3)" checkbox from the "Content types" facet
    Then I should see the "Organisation country" facet
    Then the "Organisation country" inline facet should allow selecting the following values:
      | Belgium (1)        |
      | European Union (2) |
    And I check the "Belgium (1)" checkbox from the "Organisation country" facet
    Then the page should show only the tiles "Initial Overview"
    And I should see the following facet summary "Assessment report, Belgium"
    And I uncheck the "Belgium (1)" checkbox from the "Organisation country" facet
    And I check the "European Union (2)" checkbox from the "Organisation country" facet
    Then the page should show only the tiles "Transmission Summary, Acme Report"
    And I should see the following facet summary "Assessment reports, European Union"

  Scenario: Solutions tied to assessments are not searchable.
    Given solution content:
      | title                                   | state     | collection                           |
      | some random text to search architecture | published | Solutions mentioned in an assessment |
    When I visit the advanced search page
    And I enter "architecture" in the search bar and press enter
    Then I should not see the "some random text to search architecture" tile
