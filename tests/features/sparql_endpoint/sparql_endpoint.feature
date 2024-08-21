@api @group-i
Feature: Tests related to the SPARQL query endpoint.

  Scenario: Querying the SPARQL endpoint with a valid query
    Given I go to "/sparql"
    Then I should see the heading "Query SPARQL endpoint"

    # A default query is already set and the default format is HTML.
    When I press "Submit"
    Then the response status code should be 200
    And I should see the text "SPARQL | HTML5 table"
    And I should see the following table rows:
      | s | p | o |

    Given I go to "/sparql"
    And I select "N-Triples" from "format"
    When I press "Submit"
    Then the response status code should be 200
    And the content type of the response should be "text/ntriples; charset=UTF-8"

    Given I go to "/sparql"
    And I select "Turtle" from "format"
    When I press "Submit"
    Then the response status code should be 200
    And the content type of the response should be "text/turtle; charset=UTF-8"

    Given I go to "/sparql"
    And I select "RDF/XML" from "format"
    When I press "Submit"
    Then the response status code should be 200
    And the content type of the response should be "application/rdf+xml"

    When I go to "/sparql"
    And I select "JSON" from "format"
    And I press "Submit"
    Then the response status code should be 200
    And the content type of the response should be "application/sparql-results+json"
    And the response should contain "{ \"head\": { \"link\": [], \"vars\": [\"s\", \"p\", \"o\"] }"

    Given I go to "/sparql"
    And I select "XML" from "format"
    When I press "Submit"
    Then the response status code should be 200
    And the content type of the response should be "application/sparql-results+xml"

    Given I go to "/sparql"
    And I select "CSV" from "format"
    When I press "Submit"
    Then the response status code should be 200
    And the content type of the response should be "text/csv; charset=UTF-8"

  Scenario Outline: Error messages are always text plain with a 400 status code
    Given I go to "/sparql"
    And I fill in "query" with "Invalid"
    And I select "<format>" from "format"
    And I press "Submit"
    Then the response status code should be 400
    And the content type of the response should be "text/plain; charset=UTF-8"
    And the response should contain "Virtuoso 37000 Error SP030: SPARQL compiler, line 2: syntax error at 'Invalid' before '}'"

    Examples:
      | format      |
      | HTML        |
      | Spreadsheet |
      | XML         |
      | JSON        |
      | Javascript  |
      | Turtle      |
      | RDF/XML     |
      | N-Triples   |
      | CSV         |
      | TSV         |

  Scenario: Wrong format was requested.
    # The query is valid but the format was not selected from the list and
    # was typed directly into the URL.
    Given I go to "sparql?query=SELECT%20%2A%20WHERE%20%7B%20%3Fs%20%3Fp%20%3Fo%20%7D%20LIMIT%2010&format=adfsadf"
    Then the response status code should be 400
    And I should see the text "Bad Request. Unknown format"
