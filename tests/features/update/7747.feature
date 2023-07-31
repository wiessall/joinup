@api @group-clone @javascript
Feature: Test the installation of the new EIRA version.

  Scenario: The properties block should appear in EIRA pages with relevant data.
    When I am logged in as a moderator
    And I am on the homepage
    And I click "RDF ID converter"
    And I fill in "RDF entity ID or a URL" with "http://data.europa.eu/dr8/AnalyticalCapacityValue"
    # Antibot protects the form and cannot be run without javascript.
    # This is why we need to use the '@javascript' tag.
    And I press "Go!"

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
