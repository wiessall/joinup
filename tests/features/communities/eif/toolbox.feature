@api @eif_community @terms @group-e
Feature:
  As the owner of the EIF toolbox
  in order to make it easier for users to find appropriate solutions
  I need to be able to present them in a nice searchable way.

  Background:
    Given owner:
      | name | type    |
      | ACDC | Company |
    And collections:
      | title   | state     |
      | Killjoy | validated |
      | Sage    | validated |
      | Phoenix | draft     |
    And solutions:
      | title     | eif reference                      | eif category    | collection | state     |
      | Jordan 1  | Recommendation 1, Recommendation 2 | Common services | Killjoy    | validated |
      | Jordan 2  | Recommendation 4                   | Common services | Sage       | validated |
      | Jordan 3  | Recommendation 5, Recommendation 2 | Common services | Sage       | draft     |
      | Jordan 4  | Recommendation 3, Recommendation 1 | Common services | Phoenix    | validated |
      | Jordan 5  | Recommendation 4                   | Common services | Killjoy    | draft     |

  Scenario: Present and search the solutions.
    Given I am logged in as a user with the moderator role

    When I go to the "Jordan 1" solution
    And I click "EIF perspective" in the "Entity actions" region
    Then I should see the heading "Jordan 1 EIF perspective"
    And I fill in "Description" with "It will be an amazing principle."
    And I check "Test resource"
    And I select "Underlying Principle 2: Openness" from "EIF qualifier"
    And I press "Create qualifier"
    And I press "Save"

    When I go to the "Jordan 2" solution
    And I click "EIF perspective" in the "Entity actions" region
    Then I check "Test resource"
    And I select "Underlying Principle 3: Transparency" from "EIF qualifier"
    And I press "Create qualifier"
    And I press "Save"

    When I go to the "Jordan 3" solution
    And I click "EIF perspective" in the "Entity actions" region
    Then I check "Test resource"
    And I select "Underlying Principle 11: Preservation of information" from "EIF qualifier"
    And I press "Create qualifier"
    And I press "Save"

    When I visit the "Online Assistant" custom page
    And I should see the following filter categories in the correct order:
      | Interoperability layer  |
      | Conceptual model        |
      | Underlying principles   |
      | Solution category       |
    And I should see the text "Jordan 1"
    And I should see the text "Jordan 2"
    But I should not see the text "Jordan 3"

    And I click "Underlying Principle 3: Transparency" in the "Content" region
    And I should see the text "Jordan 2"
    But I should not see the text "Jordan 3"
    And I should not see the text "Jordan 2"
