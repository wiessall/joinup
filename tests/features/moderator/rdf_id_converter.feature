@api @group-h
Feature:
  As a moderator
  When I have in my possession an RDF entity ID or URL
  I want to be able to directly navigate to the content

  Scenario: Convert a URL of a taxonomy term.
    When I am logged in as a moderator
    And I am on the homepage
    And I click "RDF URI converter"
    Then I should see the heading "Entity URI to URL and the other way around"

    # Deliberately add a blank space in the beginning and the end of the URI to ensure trimming.
    When I fill in "RDF URI or entity URL" with " http://data.europa.eu/dr8/testing/PublicServiceProvider "
    And I press "Go!"
    Then I should see the heading "Public Service Provider"

  Scenario Outline: Convert a URL of an entity.
    Given the following "collection" content:
      | uri   | title     |
      | <uri> | <heading> |

    When I am logged in as a moderator
    And I am on the homepage
    And I click "RDF URI converter"
    Then I should see the heading "Entity URI to URL and the other way around"

    # Deliberately add a blank space in the beginning and the end of the URI to ensure trimming.
    When I fill in "RDF URI or entity URL" with " <uri> "
    And I press "Go!"
    Then I should see the heading "<heading>"

    Examples:
      | uri                            | heading                                   |
      | http://test.com/example/simple | Rdf ID Converter Collection simple        |
      | http://test.com/example/       | Rdf ID Converter Collection slash         |
      | http://test.com/example#       | Rdf ID Converter Collection hash pound    |
      | http://test.com/example?       | Rdf ID Converter Collection question mark |
