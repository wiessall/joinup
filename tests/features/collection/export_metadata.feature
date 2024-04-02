@api @group-a
Feature: Export collection metadata
  As a user of Joinup I should be able to download the ADMS properties of the collections.

  # @todo ISAICP-8568, ISAICP-8569, ISAICP-8572: Add tests once entities have been converted to nodes and mapped to rdf_sync?
  Scenario: Export RDF data
    Given a "licence_type" term with the name "Sample Licence type"
    When I click "Metadata"
    Then I should see the heading "Export RDF Metadata: Sample Licence type"
    And I should see the link "JSON-LD"
    And I should see the link "Notation3"
    And I should see the link "N-Triples"
    And I should see the link "RDF/XML"
    And I should see the link "Turtle Terse RDF Triple Language"

