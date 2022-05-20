@api @eif_community @terms @group-e
Feature:
  As the owner of the EIF toolbox
  in order to make it easier for users to find appropriate solutions
  I need to be able to present them in a nice searchable way.

  @javascript
  Scenario: Present and search the solutions.
    Given owner:
      | name | type    |
      | ACDC | Company |
    And collections:
      | title   | state     |
      | Killjoy | validated |
      | Sage    | validated |
      | Phoenix | draft     |
    And solutions:
      | title    | eif reference                      | eif category    | collection | state     |
      | Jordan 1 | Recommendation 1, Recommendation 2 | Common services | Killjoy    | validated |
      | Jordan 2 | Recommendation 4                   | Common services | Sage       | validated |
      | Jordan 3 | Recommendation 5, Recommendation 2 | Common services | Sage       | draft     |
      | Jordan 4 | Recommendation 3, Recommendation 1 | Common services | Phoenix    | validated |
      | Jordan 5 | Recommendation 4                   | Common services | Killjoy    | draft     |

    # Visit the toolbox page once to ensure that solutions are properly
    # re-indexed when an EIF perspective is created or deleted.
    Given I visit the "Online Assistant" custom page
    Then I should not see the heading "Jordan 1"

    Given the following EIF perspective data for the "Jordan 1" solution:
      | Description      | It will be an amazing principle.                                   |
      | Type of solution | Common services                                                    |
      | Principle        | name: Underlying Principle 2: Openness, description: Hello world 2 |
    And the following EIF perspective data for the "Jordan 2" solution:
      | Description      | It will be an amazing principle.                                       |
      | Type of solution | Common services                                                        |
      | Principle        | name: Underlying Principle 3: Transparency, description: Hello world 3 |
    And the following EIF perspective data for the "Jordan 3" solution:
      | Description      | It will be an amazing principle.                                                        |
      | Type of solution | Common services                                                                         |
      | Principle        | name: Underlying Principle 11: Preservation of information, description: Hello world 11 |

    When I visit the "Online Assistant" custom page
    And I should see the following filter categories in the correct order:
      | Underlying principles  |
      | Interoperability layer |
      | Conceptual model       |
      | Solution category      |
    And I should see the heading "Jordan 1"
    And I should see the heading "Jordan 2"
    But I should not see the heading "Jordan 3"

    When I click "Transparency" in the "Content" region
    And I should see the heading "Jordan 2"
    But I should not see the heading "Jordan 3"
    And I should not see the heading "Jordan 1"

    # Ensure that deleting an EIF perspective, the online assistant is updated
    # as well.
    Given I delete the EIF perspective of the "Jordan 1" solution
    When I visit the "Online Assistant" custom page
    And I should not see the heading "Jordan 1"
