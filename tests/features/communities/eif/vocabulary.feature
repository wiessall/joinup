@api @group-a
Feature:
  In order to be able to have the solutions categorized properly through the EIF Toolbox
  As the collection owner
  I need to have the EIF recommendations available.

  Scenario: EIF recommendations are available to view.
    Given I am not logged in
    When I visit the page of the entity having "http://data.europa.eu/BC1" as RDF resource
    Then I should see the heading "Basic Component 1: Coordination function"
    And I should see the text "The coordination function ensures that needs are identified and appropriate services are invoked and orchestrated to provide a European public service."
