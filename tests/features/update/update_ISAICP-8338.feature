@api @group-clone
Feature: Taxonomy terms are synchronizing to RDF backend.

  Scenario: Smoke test term synchronization

    # EIRA terms.
    Given I visit the page of the entity having "http://data.europa.eu/dr8/APIApplicationInterface" as RDF resource
    Then I should see the heading "API"
    And I should see the heading "EIRA properties"
    And the following "http://data" graph triples should exist in the RDF backend:
      | <http://data.europa.eu/dr8/APIApplicationInterface> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2004/02/skos/core#Concept>                    |
      | <http://data.europa.eu/dr8/APIApplicationInterface> <http://www.w3.org/2004/02/skos/core#broaderTransitive> <http://data.europa.eu/dr8/view/TechnicalView-Application> |
      | <http://data.europa.eu/dr8/APIApplicationInterface> <http://www.w3.org/2004/02/skos/core#inScheme> <http://data.europa.eu/dr8>                                         |
      | <http://data.europa.eu/dr8/APIApplicationInterface_Properties> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2004/02/skos/core#Concept>         |
      | <http://data.europa.eu/dr8/APIApplicationInterface_Properties> <http://www.w3.org/2004/02/skos/core#related> <http://data.europa.eu/dr8/APIApplicationInterface>       |
      | <http://data.europa.eu/dr8/APIApplicationInterface_Properties> <http://www.w3.org/2004/02/skos/core#topConceptOf> <http://data.europa.eu/dr8>                          |

    # EIF terms.
    Given I visit the page of the entity having "http://publications.europa.eu/resource/authority/file-type/GDB" as RDF resource
    Then I should see the heading "Esri File Geodatabase"
    And the following "http://data" graph triples should exist in the RDF backend:
      | <http://publications.europa.eu/resource/authority/file-type/GDB> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2004/02/skos/core#Concept> |
      | <http://publications.europa.eu/resource/authority/file-type/GDB> <http://joinup.eu/file-type/extension> ".gdb"^^<http://www.w3.org/2001/XMLSchema#string>        |
      | <http://publications.europa.eu/resource/authority/file-type/GDB> <http://www.w3.org/2004/02/skos/core#prefLabel> "Esri File Geodatabase"@en                      |
