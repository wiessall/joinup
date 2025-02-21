@api @group-clone
Feature: Test EIRA pages

  Scenario: Ensure EIRA properties are shown and cached properly.
    When I visit the page of the entity having "http://data.europa.eu/dr8/WebAppBusinessInterface" as RDF resource
    Then I should see the heading "Web App"
    And I should not see the heading "EIRA properties"

    When I visit the page of the entity having "http://data.europa.eu/dr8/ServiceAppBusinessInterface" as RDF resource
    Then I should see the heading "Service App"
    And I should see the text "Definition: Service APP ABB is a Business Interface is a a type of software application that provides specific functionality to other applications or users within a larger system."
    And I should see the heading "EIRA properties"

    And I should see the following table rows:
      | Namespace      | URI                                                   |
      | rdf:           | http://www.w3.org/1999/02/22-rdf-syntax-ns#           |
      | skos:          | http://www.w3.org/2004/02/skos/core#                  |
      | dct:           | http://purl.org/dc/terms/                             |
      | eira:          | http://data.europa.eu/dr8/                            |
      | Property       | Value                                                 |
      | rdf:type       | http://www.w3.org/2004/02/skos/core#Concept           |
      | skos:notation  | Properties of Service App                             |
      | dct:identifier | http://data.europa.eu/dr8/ServiceAppBusinessInterface |
      | skos:prefLabel | Properties of Service App                             |
      | dct:type       | eira:ServiceAppBusinessInterface                      |
      | dct:modified   | 2024-01-28                                            |
