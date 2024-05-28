@api @group-clone
Feature: Test EIRA pages

  Scenario: Ensure EIRA properties are shown and cached properly.
    When I visit the page of the entity having "http://data.europa.eu/dr8/ABB_Status" as RDF resource
    Then I should see the heading "ABB_Status"
    And I should not see the heading "EIRA properties"

    When I visit the page of the entity having "http://data.europa.eu/dr8/DescriptiveMetadata" as RDF resource
    Then I should see the heading "Descriptive Metadata"
    And I should see the text "The Descriptive Metadata ABB is salient for semantic interoperability because it facilitates opening and sharing data by providing the appropriate format"
    And I should see the heading "EIRA properties"

    And I should see the following table rows:
      | Namespace       | URI                                                      |
      | rdf:            | http://www.w3.org/1999/02/22-rdf-syntax-ns#              |
      | skos:           | http://www.w3.org/2004/02/skos/core#                     |
      | dct:            | http://purl.org/dc/terms/                                |
      | owl:            | http://www.w3.org/2002/07/owl#                           |
      | eira:           | http://data.europa.eu/dr8/                               |
      | Property        | Value                                                    |
      | rdf:type        | http://www.w3.org/2004/02/skos/core#Concept              |
      | skos:notation   | Properties of Descriptive Metadata                       |
      | dct:identifier  | http://data.europa.eu/dr8/DescriptiveMetadata_Properties |
      | skos:prefLabel  | Properties of Descriptive Metadata                       |
      | owl:deprecated  | true                                                     |
      | dct:type        | eira:DescriptiveMetadata                                 |
      | dct:modified    | 2023-05-25                                               |
      | eira:ABB_Status | Obsolete                                                 |
      | eira:ID         | ABB195                                                   |
