@api @group-f
Feature: Tests related to the SPARQL query endpoint.

  Scenario: Querying the SPARQL endpoint with a valid query
    Given I go to "/sparql"
    Then I should see the heading "Query SPARQL endpoint"

    # A default query is already set and the default format is HTML.
    When I click "Submit"
    Then the response status code should be 200
    And I should see the heading "Results"
    And I should see the text "SPARQL | HTML5 table"
    And I should see the following table rows:
      | ?s                                | ?p                                              | ?o                                                  |
      | http://purl.org/dc/terms/modified | http://www.w3.org/1999/02/22-rdf-syntax-ns#type | http://www.w3.org/1999/02/22-rdf-syntax-ns#Property |

    Given I select "JSON" from "format"
    When I click "Submit"
    Then the response status code should be 200
    And the content type of the response should be "application/sparql-results+json"
    And the response should contain "\"results\": {"
    And the response should contain "\"head\": {"
