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

