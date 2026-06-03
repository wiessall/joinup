@api @group-clone @wip
Feature: As an anonymous user, when I visit /data/dr8, I should get the cached
  EIRA vocabulary, serialized as RDF/XML.

  Scenario: Test the content and the caching.

    When go to "/data/dr8"
    Then the output should match the file contents of "../../../resources/vocab/EIRA v6.1.0_SKOS.rdf"
    # Check if the page is cached.
    When I reload the page
    Then the page should be cached
    And the output should match the file contents of "../../../resources/vocab/EIRA v6.1.0_SKOS.rdf"

    When go to "/data/dr8/egovera"
    Then the output should match the file contents of "../../../resources/vocab/eGovERA.rdf"
    # Check if the page is cached.
    When I reload the page
    Then the page should be cached
    And the output should match the file contents of "../../../resources/vocab/eGovERA.rdf"
