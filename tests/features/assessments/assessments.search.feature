@api @group-a @javascript
Feature: Assessment report search functionality
  Test search and filtering capabilities for assessment reports

  Background:
    Given public_organisation assessments:
      | name                  | organisation_type          |
      | Bluewave Technologies | european_union_institution |

  Scenario: Advanced search by content type and keyword
    Given report assessments:
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
