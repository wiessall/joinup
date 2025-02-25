@api @group-clone
Feature:
  In order to better read the eira descriptions
  As a user of the website
  I need the description to be shown in a nice way.

  Scenario: Apply a formatter to the description field that allows the description to be properly formatted.
    When I am logged in as a moderator
    And I am on the homepage
    And I click "RDF URI converter"
    And I fill in "RDF URI or entity URL" with "http://data.europa.eu/dr8/ServiceAppBusinessInterface"
    And I press "Go!"
    Then I should see the heading "Service App"
    # The <p> tags are not part of the original description.
    And the response should contain "<p>EIRA concept: eira:ArchitectureBuildingBlock</p>"
    And the response should contain "<p>ABB name: eira:ServiceAppBusinessInterface</p>"
