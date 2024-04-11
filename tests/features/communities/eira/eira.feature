@api @group-e
Feature: As an anonymous user, when I visit /data/dr8, I should get the cached
  EIRA vocabulary, serialized as RDF/XML.

  Scenario: Test the content and the caching.

    When go to "/data/dr8"
    Then the output should match the file contents of "../../../resources/vocab/EIRA_SKOS.rdf"
    # Check if the page is cached.
    When I reload the page
    Then the page should be cached
    And the output should match the file contents of "../../../resources/vocab/EIRA_SKOS.rdf"

  Scenario: The properties block should appear in EIRA pages with relevant data.
    When I am logged in as a moderator
    And I am on the homepage
    And I click "RDF ID converter"
    When I visit the page of the entity having "http://data.europa.eu/dr8/AnalyticalCapacityValue" as RDF resource

    Then I should see the heading "Analytical Capacity"
    And I should see the heading "EIRA properties"

    And I should see the following table rows:
      | Namespace     | URI                                               |
      | rdf:          | http://www.w3.org/1999/02/22-rdf-syntax-ns#       |
      | dct:          | http://purl.org/dc/terms/                         |
      | skos:         | http://www.w3.org/2004/02/skos/core#              |
      | eira:         | http://data.europa.eu/dr8/                        |
      | Property      | Value                                             |
      | skos:notation | Properties of Analytical Capacity                 |
      | eira:PURI     | http://data.europa.eu/dr8/AnalyticalCapacityValue |
      | eira:concept  | eira:ArchitectureBuildingBlock                    |
