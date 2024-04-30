@api @group-e
Feature:
  In order to be able to have the solutions categorized properly through the EIF Toolbox
  As the collection owner
  I need to have the EIF recommendations available.

  Scenario: EIF recommendations are available to view.
    Given I am not logged in
    When I visit the page of the entity having "http://data.europa.eu/BC1" as RDF resource
    Then I should see the heading "Basic Component 1: Coordination function"
    And I should see the text "The coordination function ensures that needs are identified and appropriate services are invoked and orchestrated to provide a European public service."

  Scenario: EIF recommendations field is accessible to moderators only.
    Given collection:
      | title | EIF Toolbox |
      | state | published   |
    And solution:
      | title      | Some EIF solution |
      | state      | published         |
      | collection | EIF Toolbox       |

    Given I am logged in as a facilitator of the "Some EIF solution" solution
    When I go to the edit form of the "Some EIF solution" solution
    Then the following fields should not be present "EIF reference, EIF category"

    Given I am logged in as a moderator
    When I go to the edit form of the "Some EIF solution" solution
    Then the following fields should be present "EIF reference, EIF category"

    When I press "Publish"

  Scenario: EIF recommendations are not visible to the end user.
    Given collection:
      | title | EIF Toolbox |
      | state | published   |
    And solution:
      | title         | Some EIF solution |
      | state         | published         |
      | collection    | EIF Toolbox       |
      | eif reference | Recommendation 1  |
      | eif category  | Common services   |

    When I go to the "Some EIF solution" solution
    Then I should not see the text "EIF reference"

    When I go to "/solutions"
    Then I should see the "Some EIF solution" tile
    But I should not see the text "EIF reference"

  Scenario: Solutions referencing an EIF term should appear in the corresponding page.
    Given collection:
      | title | EIF Toolbox |
      | state | published   |
    And solutions:
      | title    | state     | topic      | collection  | eif reference                      |
      | Balker   | published |            | EIF Toolbox | Recommendation 1, Recommendation 2 |
      | Corridor | published |            | EIF Toolbox | Recommendation 1                   |
      | Lager    | published | Demography | EIF Toolbox |                                    |

    # Underlying Principle 1.
    When I visit the page of the entity having "http://data.europa.eu/Rec1" as RDF resource
    Then I should see the "Balker" tile
    And I should see the "Corridor" tile
    But I should not see the "Lager" tile

    # Underlying Principle 2
    When I visit the page of the entity having "http://data.europa.eu/Rec2" as RDF resource
    Then I should see the "Balker" tile
    But I should not see the "Corridor" tile
    And I should not see the "Lager" tile
