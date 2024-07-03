@api @group-a
Feature: Export collection metadata
  As a user of Joinup I should be able to download the ADMS properties of the collections.

  Scenario: Export RDF data
    Given a "licence_type" term with the name "Sample Licence type"
    When I click "Metadata"
    Then I should see the heading "Download the Sample Licence type taxonomy term as:"
    And I should see the link "JSON-LD"
    And I should see the link "Notation3"
    And I should see the link "N-Triples"
    And I should see the link "RDF/XML"
    And I should see the link "Turtle Terse RDF Triple Language"

  Scenario: Distribution nodes can be exported as RDF.
    Given solutions:
      | title      | state     |
      | Solution 1 | published |
    Given distribution content:
      | title          | description   | access_url        | state     | parent     |
      | Distribution 1 | Description 1 | http://access.url | published | Solution 1 |
    When I go to the "Distribution 1" distribution
    When I click "Metadata"
    Then I should see the heading "Download the Distribution 1 content item as:"
    And I should see the link "JSON-LD"
    And I should see the link "Notation3"
    And I should see the link "N-Triples"
    And I should see the link "RDF/XML"
    And I should see the link "Turtle Terse RDF Triple Language"
    When I click "JSON-LD"
    Then the response should contain "http://www.w3.org/ns/dcat#Distribution"
    And the response should contain "\"http://purl.org/dc/terms/title\":[{\"@value\":\"Distribution 1\""
